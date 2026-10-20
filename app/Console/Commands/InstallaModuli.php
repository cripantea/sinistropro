<?php

namespace App\Console\Commands;

use App\Models\FieldDictionaryEntry;
use App\Models\ModuleTemplate;
use App\Models\Tenant;
use App\Services\PdfFormFillerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sostituisce in un colpo i modelli PDF di un tenant con le versioni nuove (PDF + schema campi
 * già posizionato) e compila il dizionario campi. Prima di toccare qualcosa salva un backup.
 *
 * La cartella contiene, per ogni modulo, NOME.pdf e NOME.json (schema campi). Ogni file viene
 * abbinato al modello esistente del tenant il cui nome contiene la parola indicata in REGOLE.
 */
class InstallaModuli extends Command
{
    protected $signature = 'app:installa-moduli
                            {cartella : Cartella con i PDF e gli schemi JSON}
                            {--tenant= : ID del tenant (obbligatorio)}
                            {--dry-run : Mostra cosa cambierebbe senza modificare nulla}';

    protected $description = 'Carica i nuovi modelli PDF con i campi già posizionati e compila il dizionario campi (con backup).';

    /** Parola nel nome del modello esistente → file nella cartella. */
    private const REGOLE = [
        '/denuncia/i'                    => 'MODULO_DENUNCIA_SINISTRO_MALACRIDA',
        '/dichiaraz|scopert/i'           => 'DICHIARAZIONE_SCOPERTI_ASSICURATIVI_MALACRIDA',
        '/cession/i'                     => 'MODULO_CESSIONE_CREDITO_MALACRIDA',
        '/necessit|giustific|noleggio/i' => 'GIUSTIFICATIVO_NOLEGGIO_MALACRIDA',
    ];

    /** Campi che vengono dall'anagrafica cliente. */
    private const DAL_CLIENTE = [
        'cognome_nome' => 'nome', 'nome_cognome' => 'nome', 'indirizzo_residenza' => 'indirizzo', 'cap' => 'cap', 'citta' => 'citta',
        'provincia' => 'provincia', 'telefono' => 'telefono', 'email' => 'email', 'codice_fiscale' => 'codice_fiscale',
    ];

    public function handle(PdfFormFillerService $pdf): int
    {
        $dry    = (bool) $this->option('dry-run');
        $dir    = rtrim((string) $this->argument('cartella'), '/');
        $tenant = Tenant::find((int) $this->option('tenant'));

        if (! $tenant) {
            $this->error('Indica il tenant con --tenant=ID.');
            return self::FAILURE;
        }
        if (! is_dir($dir)) {
            $this->error("Cartella non trovata: {$dir}");
            return self::FAILURE;
        }

        $this->info(($dry ? '[SIMULAZIONE] ' : '') . "Tenant #{$tenant->id} {$tenant->name}");
        $modelli = ModuleTemplate::acrossAllTenants()->where('tenant_id', $tenant->id)->get();

        // ── 1. Abbinamento file ↔ modelli esistenti e controlli ─────────────────────────
        $piano = [];
        foreach (self::REGOLE as $regola => $base) {
            $trovati = $modelli->filter(fn (ModuleTemplate $m) => preg_match($regola, $m->name));
            if ($trovati->count() !== 1) {
                $this->error("Per {$base} serve esattamente un modello che corrisponda a {$regola}: trovati {$trovati->count()} ("
                    . $trovati->pluck('name')->join(', ') . ').');
                return self::FAILURE;
            }
            $fPdf = "{$dir}/{$base}.pdf";
            $fJson = "{$dir}/{$base}.json";
            if (! is_file($fPdf) || ! is_file($fJson)) {
                $this->error("Mancano {$base}.pdf o {$base}.json nella cartella.");
                return self::FAILURE;
            }
            $schema = json_decode((string) file_get_contents($fJson), true);
            if (! is_array($schema) || $schema === []) {
                $this->error("{$base}.json non è uno schema valido.");
                return self::FAILURE;
            }
            $contenuto = (string) file_get_contents($fPdf);
            $pagine = $pdf->pageCount($contenuto);
            $maxPagina = (int) max(array_map(fn ($c) => max([(int) ($c['page'] ?? 1), ...array_map(fn ($p) => (int) ($p['page'] ?? 1), $c['positions'] ?? [])]), $schema));
            if ($pagine !== null && $maxPagina > $pagine) {
                $this->error("{$base}: lo schema usa la pagina {$maxPagina} ma il PDF ne ha {$pagine}.");
                return self::FAILURE;
            }

            $modello = $trovati->first();
            $vecchi = array_unique(array_column($modello->fields_schema ?? [], 'name'));
            $nuovi = array_unique(array_column($schema, 'name'));
            $piano[] = compact('modello', 'base', 'schema', 'contenuto', 'pagine');

            $this->newLine();
            $this->line("• {$modello->name} (#{$modello->id}) ← {$base}.pdf · " . ($pagine ?? '?') . ' pagine · ' . count($nuovi) . ' campi');
            $tolti = array_diff($vecchi, $nuovi);
            $aggiunti = array_diff($nuovi, $vecchi);
            $this->line('    campi che restano: ' . count(array_intersect($vecchi, $nuovi)));
            $this->line('    campi nuovi: ' . ($aggiunti ? implode(', ', $aggiunti) : 'nessuno'));
            $this->line('    campi tolti: ' . ($tolti ? implode(', ', $tolti) : 'nessuno'));
        }

        // ── 2. Dizionario campi ────────────────────────────────────────────────────────
        $dizionario = $this->dizionario($tenant, array_merge(...array_column($piano, 'schema')));
        $esistenti = FieldDictionaryEntry::acrossAllTenants()->where('tenant_id', $tenant->id)->get()->keyBy('key');
        $this->newLine();
        $this->line('Dizionario campi: ' . count($dizionario) . ' voci');
        foreach ($dizionario as $voce) {
            $prima = $esistenti->get($voce['key']);
            $fonte = match (true) {
                $voce['source_type'] === 'cliente'       => "dal cliente ({$voce['source_field']})",
                $voce['source_type'] === 'pratica_field' => "dal sinistro ({$voce['source_field']})",
                in_array($voce['type'], ['checkbox', 'radio'], true) => 'a mano',
                default => 'riconosciuto dal nome (veicolo, date…) o a mano',
            };
            $stato = ! $prima ? 'nuova' : (($prima->source_type === $voce['source_type'] && $prima->source_field === $voce['source_field']
                && $prima->type === $voce['type'] && $prima->label === $voce['label']) ? 'uguale' : 'aggiornata');
            $this->line("    {$voce['key']} ({$voce['type']}): {$fonte} [{$stato}]");
        }

        if ($dry) {
            $this->newLine();
            $this->info('[SIMULAZIONE] Niente è stato modificato.');
            return self::SUCCESS;
        }

        // ── 3. Backup di modelli e dizionario attuali ──────────────────────────────────
        $backup = storage_path('app/backup-moduli-tenant' . $tenant->id . '-' . now()->format('Ymd-His') . '.json');
        $scritto = @file_put_contents($backup, json_encode([
            'modelli' => $modelli->map->only(['id', 'name', 'pdf_template_s3_key', 'output_document_category_id', 'fields_schema', 'font_size'])->values(),
            'dizionario' => $esistenti->values()->map->only(['key', 'label', 'type', 'source_type', 'source_field']),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($scritto === false) {
            $this->error("Backup non scritto ({$backup}): non modifico niente.");
            return self::FAILURE;
        }
        $this->newLine();
        $this->info("Backup salvato: {$backup}");

        // ── 4. PDF su S3, poi modelli e dizionario in una transazione ──────────────────
        foreach ($piano as $i => $p) {
            $key = "pdf_templates/tenant_{$tenant->id}/" . Str::uuid() . '.pdf';
            Storage::disk('s3')->put($key, $p['contenuto'], 'private');
            if (! Storage::disk('s3')->exists($key)) {
                $this->error("Caricamento di {$p['base']}.pdf non riuscito: non modifico niente.");
                return self::FAILURE;
            }
            $piano[$i]['key'] = $key;
        }

        DB::transaction(function () use ($piano, $dizionario, $tenant): void {
            foreach ($piano as $p) {
                $p['modello']->update([
                    'pdf_template_s3_key' => $p['key'],
                    'fields_schema'       => $p['schema'],
                    'font_size'           => 10,
                ]);
            }
            foreach ($dizionario as $voce) {
                FieldDictionaryEntry::acrossAllTenants()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'key' => $voce['key']],
                    $voce + ['tenant_id' => $tenant->id],
                );
            }
        });

        $this->info('Fatto: ' . count($piano) . ' modelli aggiornati, ' . count($dizionario) . ' voci nel dizionario. I vecchi PDF restano su S3 (si torna indietro con il backup).');

        return self::SUCCESS;
    }

    /**
     * Una voce per nome di campo: dati del cliente dall'anagrafica, campi del sinistro dal campo
     * personalizzato con lo stesso nome o la stessa etichetta, il resto con la compilazione automatica.
     *
     * @return array<int, array{key: string, label: string, type: string, source_type: string, source_field: ?string}>
     */
    private function dizionario(Tenant $tenant, array $campi): array
    {
        $campiSinistro = $tenant->getCustomFieldsSchema();
        $voci = [];
        foreach ($campi as $c) {
            $key = $c['name'];
            if (isset($voci[$key])) {
                continue;
            }
            [$tipo, $fonte] = ['manual', null];
            if (isset(self::DAL_CLIENTE[$key])) {
                [$tipo, $fonte] = ['cliente', self::DAL_CLIENTE[$key]];
            } elseif (! in_array($c['type'], ['checkbox', 'radio'], true)) {
                foreach ($campiSinistro as $s) {
                    if ($s['name'] === $key || self::confrontabile($s['label'] ?? '') === self::confrontabile($c['label'])) {
                        [$tipo, $fonte] = ['pratica_field', $s['name']];
                        break;
                    }
                }
            }
            $voci[$key] = ['key' => $key, 'label' => mb_substr($c['label'], 0, 255), 'type' => $c['type'], 'source_type' => $tipo, 'source_field' => $fonte];
        }

        return array_values($voci);
    }

    /** Stesso confronto della finestra di compilazione (minuscolo, senza accenti e preposizioni). */
    private static function confrontabile(string $s): string
    {
        $s = mb_strtolower(Str::ascii($s));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = preg_replace('/\b(di|del|della|dello|dei|delle|il|lo|la|i|gli|le)\b/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
