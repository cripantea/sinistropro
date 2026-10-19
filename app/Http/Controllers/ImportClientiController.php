<?php

namespace App\Http\Controllers;

use App\Jobs\ImportaClientiJob;
use App\Models\Importazione;
use App\Services\ImportClienti\ClientiImporter;
use App\Services\ImportClienti\ImportFileReader;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zona di importazione clienti: si carica il file completo esportato dal gestionale
 * e vengono aggiunti solo i clienti (e i veicoli) che non ci sono ancora.
 *
 * 1. carica  → il file viene letto una volta e salvato come righe JSON (bozza)
 * 2. analizza → simulazione con la mappatura scelta: quanti nuovi, quanti già presenti
 * 3. esegui  → l'import vero gira in coda; la pagina ne segue lo stato
 */
class ImportClientiController extends Controller
{
    private const MAX_FILE_MB = 50;
    private const PREVIEW_ROWS = 5;

    public function create(): Response
    {
        $schema = $this->schema();

        return Inertia::render('Clienti/Import', [
            'targets' => ClientiImporter::targets($schema),
            'formats' => ImportFileReader::EXTENSIONS,
            'maxMb'   => self::MAX_FILE_MB,
            'storico' => Importazione::with('user:id,name')
                ->where('stato', '!=', 'bozza')
                ->latest()->limit(10)
                ->get(['id', 'user_id', 'file_name', 'stato', 'riepilogo', 'errore', 'created_at', 'completata_at']),
        ]);
    }

    /** Step 1: legge il file e propone la mappatura delle colonne. */
    public function preview(Request $request, ImportFileReader $reader): JsonResponse
    {
        $this->valida($request, [
            'file' => ['required', 'file', 'max:' . (self::MAX_FILE_MB * 1024)],
        ], [
            'file.required' => 'Nessun file ricevuto: potrebbe superare il limite di caricamento del server.',
            'file.max'      => 'Il file supera i ' . self::MAX_FILE_MB . ' MB.',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ImportFileReader::EXTENSIONS, true)) {
            return response()->json(['error' => 'Formato non supportato. Usa Excel (.xls, .xlsx), CSV, JSON o XML.'], 422);
        }

        if (! TenantContext::id()) {
            return response()->json(['error' => 'Seleziona prima un tenant.'], 422);
        }

        $this->pulisciBozzeVecchie();

        // Un Excel da 10.000 righe occupa più dei 128 MB di default mentre viene letto.
        ini_set('memory_limit', '512M');

        try {
            $table = $reader->read($file->getRealPath(), $extension);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Impossibile leggere il file: ' . $e->getMessage()], 422);
        }

        if ($table['rows'] === []) {
            return response()->json(['error' => 'Il file è vuoto o non contiene righe valide.'], 422);
        }

        $importazione = Importazione::create([
            'tenant_id' => TenantContext::id(),
            'user_id'   => auth()->id(),
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_id'   => (string) Str::uuid(),
            'stato'     => 'bozza',
        ]);
        Storage::disk('local')->put($importazione->rowsPath(), json_encode($table['rows'], JSON_UNESCAPED_UNICODE));

        $saved = TenantContext::tenant()?->getSetting('import_clienti.mapping', []) ?? [];

        return response()->json([
            'importazione_id' => $importazione->id,
            'headers'         => $table['headers'],
            'preview'         => array_slice($table['rows'], 0, self::PREVIEW_ROWS),
            'total'           => count($table['rows']),
            'mapping'         => ClientiImporter::suggestMapping($table['headers'], $this->schema(), $saved),
        ]);
    }

    /** Step 2: simulazione, nessuna scrittura. */
    public function analizza(Request $request, Importazione $importazione, ClientiImporter $importer): JsonResponse
    {
        $data = $this->validaMappatura($request);

        if ($importazione->stato !== 'bozza') {
            return response()->json(['error' => 'Questa importazione è già stata avviata.'], 422);
        }
        $rows = $this->rows($importazione);
        if ($rows === null) {
            return response()->json(['error' => 'File non più disponibile. Ricaricalo.'], 422);
        }

        ini_set('memory_limit', '512M');
        $result = $importer->process(TenantContext::id(), $this->schema(), $rows, $data['mapping'], $data['opzioni'], dryRun: true);

        $importazione->update(['mapping' => $data['mapping'], 'opzioni' => $data['opzioni']]);

        return response()->json($result);
    }

    /** Step 3: avvia l'import in coda. */
    public function esegui(Request $request, Importazione $importazione): JsonResponse
    {
        $data = $this->validaMappatura($request);

        if (! Storage::disk('local')->exists($importazione->rowsPath())) {
            return response()->json(['error' => 'File non più disponibile. Ricaricalo.'], 422);
        }

        // Un import alla volta per tenant, e la stessa bozza non parte due volte.
        $avviata = DB::transaction(function () use ($importazione, $data): bool {
            $inCorso = Importazione::whereIn('stato', ['in_coda', 'in_corso'])->lockForUpdate()->exists();
            if ($inCorso) {
                return false;
            }
            return Importazione::whereKey($importazione->id)->where('stato', 'bozza')->update([
                'stato'   => 'in_coda',
                'mapping' => json_encode($data['mapping']),
                'opzioni' => json_encode($data['opzioni']),
            ]) === 1;
        });

        if (! $avviata) {
            return response()->json(['error' => 'C\'è già un\'importazione in corso. Attendi che finisca.'], 422);
        }

        // La prossima volta lo stesso file del gestionale si mappa da solo.
        $tenant = TenantContext::tenant();
        $settings = $tenant->settings ?? [];
        data_set($settings, 'import_clienti.mapping', array_filter($data['mapping']));
        $tenant->update(['settings' => $settings]);

        ImportaClientiJob::dispatch($importazione->id);

        return response()->json(['ok' => true]);
    }

    /** Stato dell'import, per seguirne l'avanzamento. */
    public function stato(Importazione $importazione): JsonResponse
    {
        return response()->json($importazione->only(['id', 'stato', 'riepilogo', 'avvisi', 'errore', 'completata_at']));
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function schema(): array
    {
        return TenantContext::tenant()?->getClienteCustomFieldsSchema() ?? [];
    }

    /** @return array{mapping: array<string, string>, opzioni: array{aggiorna_esistenti: bool, abbinamenti: array<int, string>}} */
    private function validaMappatura(Request $request): array
    {
        $targets = array_column(ClientiImporter::targets($this->schema()), 'value');

        $this->valida($request, [
            'mapping'            => ['required', 'array'],
            'mapping.*'          => ['nullable', 'string', Rule::in($targets)],
            'aggiorna_esistenti' => ['boolean'],
            'abbinamenti'        => ['nullable', 'array'],
            'abbinamenti.*'      => ['nullable', 'string', 'max:50'],
        ], [
            'mapping.*.in' => 'Campo di destinazione non valido.',
        ]);

        $mapping = array_map(fn ($v) => (string) $v, $request->input('mapping'));
        if (! in_array('nome', $mapping, true)) {
            abort(response()->json(['error' => 'Indica quale colonna contiene il nome del cliente.'], 422));
        }

        return [
            'mapping' => $mapping,
            'opzioni' => [
                'aggiorna_esistenti' => $request->boolean('aggiorna_esistenti', true),
                // id cliente già presente → codice del file ('' = non abbinare): le correzioni fatte a mano nella verifica
                'abbinamenti'        => collect($request->input('abbinamenti', []))
                    ->filter(fn ($v, $k) => ctype_digit((string) $k))
                    ->map(fn ($v) => trim((string) $v))->all(),
            ],
        ];
    }

    /** La pagina parla in JSON: gli errori di validazione tornano come {error}, non come redirect. */
    private function valida(Request $request, array $rules, array $messages = []): void
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            abort(response()->json(['error' => $validator->errors()->first()], 422));
        }
    }

    private function rows(Importazione $importazione): ?array
    {
        $json = Storage::disk('local')->get($importazione->rowsPath());

        return $json === null ? null : json_decode($json, true);
    }

    /** Bozze abbandonate da più di un giorno: via il file con i dati personali e il record. */
    private function pulisciBozzeVecchie(): void
    {
        Importazione::where('stato', 'bozza')->where('created_at', '<', now()->subDay())->get()
            ->each(function (Importazione $imp): void {
                Storage::disk('local')->delete($imp->rowsPath());
                $imp->delete();
            });
    }
}
