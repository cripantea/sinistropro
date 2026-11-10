<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Tenant;
use App\Models\WhatsappSession;
use App\Models\WhatsappTemplate;
use App\Services\AutomationPlanner;
use App\Services\FusionWaTemplates;
use App\Support\AccessoImpostazioni;
use App\Support\TestoTemplateWhatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Impostazioni → Template WhatsApp: come in Fusion CRM, ma con le variabili di SinistroPro. */
class WhatsappTemplateController extends Controller
{
    public function __construct(private readonly FusionWaTemplates $fusionWa) {}

    public function index(): Response
    {
        $tenant = AccessoImpostazioni::tenant();

        return Inertia::render('Impostazioni/WhatsappTemplate', [
            'templates' => WhatsappTemplate::where('tenant_id', $tenant->id)->orderBy('name')->get()->map->perUi(),
            'collegato' => $this->collegato($tenant),
            'variabili' => collect(AutomationPlanner::VARIABILI_PRATICA)->map(fn ($l, $k) => ['codice' => $k, 'etichetta' => $l])->values()
                ->merge(collect($tenant->getCustomFieldsSchema())->map(fn ($f) => ['codice' => '{campi_custom.'.$f['name'].'}', 'etichetta' => $f['label']]))->all(),
            'usati' => Automation::where('tenant_id', $tenant->id)->whereNotNull('whatsapp_template_id')->get(['id', 'name', 'whatsapp_template_id'])
                ->groupBy('whatsapp_template_id')->map(fn ($l) => $l->pluck('name'))->all(),
        ]);
    }

    /** Riprende da Meta l'elenco con gli stati aggiornati. */
    public function sincronizza(): RedirectResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        abort_unless($this->collegato($tenant), 409, 'Collega prima il numero WhatsApp.');

        try {
            $remoti = $this->fusionWa->elenco($tenant->id);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Sincronizzazione non riuscita: '.$e->getMessage());
        }

        $visti = [];
        foreach ($remoti as $r) {
            if (empty($r['name'])) {
                continue;
            }
            $t = WhatsappTemplate::firstOrNew(['tenant_id' => $tenant->id, 'name' => $r['name'], 'language' => $r['language'] ?? 'it']);
            $bodyCambiato = $t->exists && $t->body !== ($r['body'] ?? '');
            $t->fill([
                'external_id' => $r['externalId'] ?? $t->external_id,
                'category' => $r['category'] ?? $t->category ?? 'UTILITY',
                'status' => in_array($r['status'] ?? null, array_keys(WhatsappTemplate::STATI), true) ? $r['status'] : 'PENDING',
                'body' => (string) ($r['body'] ?? ''),
                'variable_count' => (int) ($r['variableCount'] ?? 0),
                'header_text' => $r['headerText'] ?? null,
                'footer_text' => $r['footerText'] ?? null,
            ]);
            // Modificato fuori da SinistroPro: la versione "con i nomi" non vale più.
            if ($bodyCambiato) {
                $t->corpo = null;
            }
            $t->save();
            $visti[] = $t->id;
        }
        WhatsappTemplate::where('tenant_id', $tenant->id)->whereNotIn('id', $visti)->delete();

        return back()->with('success', count($visti).' template sincronizzati.');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        abort_unless($this->collegato($tenant), 409, 'Collega prima il numero WhatsApp.');
        $dati = $this->valida($request, $tenant);

        if (WhatsappTemplate::where('tenant_id', $tenant->id)->where('name', $dati['name'])->where('language', $dati['language'])->exists()) {
            throw ValidationException::withMessages(['name' => 'Esiste già un template con questo nome.']);
        }

        try {
            $remoto = $this->fusionWa->crea($tenant->id, $this->perMeta($dati) + ['name' => $dati['name'], 'language' => $dati['language'], 'category' => $dati['category']]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['corpo' => 'Meta ha rifiutato il template: '.$e->getMessage()]);
        }

        WhatsappTemplate::create([
            'tenant_id' => $tenant->id,
            'external_id' => $remoto['externalId'] ?? null,
            'name' => $dati['name'],
            'language' => $dati['language'],
            'category' => $remoto['category'] ?? $dati['category'],
            'status' => $remoto['status'] ?? 'PENDING',
            ...$this->locale($dati),
        ]);

        return back()->with('success', 'Template inviato a Meta: di solito l\'approvazione arriva in pochi minuti, a volte qualche ora.');
    }

    public function update(Request $request, WhatsappTemplate $template): RedirectResponse
    {
        $tenant = $this->proprio($template);
        abort_if($template->status === 'PENDING', 422, 'Il template è in revisione da Meta: si può modificare quando c\'è l\'esito.');
        $dati = $this->valida($request, $tenant, $template);

        try {
            $remoto = $this->fusionWa->modifica($tenant->id, $this->perMeta($dati) + array_filter([
                'externalId' => $template->external_id,
                'name' => $template->name,
                'category' => $template->approvato() ? null : $dati['category'],
            ]));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['corpo' => 'Meta ha rifiutato la modifica: '.$e->getMessage()]);
        }

        $template->update([
            'status' => $remoto['status'] ?? 'PENDING',
            'category' => $remoto['category'] ?? $template->category,
            'status_reason' => null,
            ...$this->locale($dati),
        ]);

        return back()->with('success', 'Modifica inviata a Meta: il template torna in revisione.');
    }

    public function destroy(Request $request, WhatsappTemplate $template): RedirectResponse
    {
        $tenant = $this->proprio($template);
        $usato = Automation::where('tenant_id', $tenant->id)->where('whatsapp_template_id', $template->id)->pluck('name');
        if ($usato->isNotEmpty() && ! $request->boolean('forza')) {
            return back()->with('error', 'Il template è usato da: '.$usato->join(', ').'. Togli prima il template da queste automazioni.');
        }

        try {
            $this->fusionWa->elimina($tenant->id, $template->name, $template->external_id);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Eliminazione non riuscita: '.$e->getMessage());
        }
        $template->delete();

        return back()->with('success', 'Template eliminato (anche su Meta).');
    }

    private function valida(Request $request, Tenant $tenant, ?WhatsappTemplate $template = null): array
    {
        $request->merge(['name' => TestoTemplateWhatsapp::nome((string) ($template?->name ?? $request->input('name')))]);
        $dati = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['nullable', 'string', 'max:10'],
            'category' => ['required', Rule::in(['UTILITY', 'MARKETING'])],
            'header_text' => ['nullable', 'string', 'max:60'],
            'corpo' => ['required', 'string', 'max:1024'],
            'footer_text' => ['nullable', 'string', 'max:60'],
        ], [
            'name.required' => 'Dai un nome al template (es. sollecito_documenti).',
            'name.regex' => 'Solo lettere minuscole, numeri e _ (es. sollecito_documenti).',
            'corpo.required' => 'Scrivi il messaggio.',
            'corpo.max' => 'Il messaggio può avere al massimo 1024 caratteri.',
        ]);
        $dati['language'] = $template?->language ?? ($dati['language'] ?? null ?: 'it');

        return $dati + TestoTemplateWhatsapp::converti($dati['corpo'], $tenant->name);
    }

    private function perMeta(array $dati): array
    {
        return array_filter([
            'headerText' => $dati['header_text'] ?? null,
            'bodyText' => $dati['body'],
            'bodyExamples' => $dati['esempi'],
            'footerText' => $dati['footer_text'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function locale(array $dati): array
    {
        return [
            'body' => $dati['body'],
            'corpo' => $dati['corpo'],
            'variabili' => $dati['variabili'],
            'variable_count' => count($dati['esempi']),
            'header_text' => $dati['header_text'] ?? null,
            'footer_text' => $dati['footer_text'] ?? null,
        ];
    }

    private function proprio(WhatsappTemplate $template): Tenant
    {
        $tenant = AccessoImpostazioni::tenant();
        abort_unless($template->tenant_id === $tenant->id, 404);

        return $tenant;
    }

    private function collegato(Tenant $tenant): bool
    {
        return WhatsappSession::where('tenant_id', $tenant->id)->where('status', 'active')->whereNotNull('waba_id')->exists();
    }
}
