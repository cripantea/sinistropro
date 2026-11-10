<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Mail\AutomazioneNotificaMail;
use App\Models\Automation;
use App\Models\Contatto;
use App\Models\Cliente;
use App\Models\DocumentCategory;
use App\Models\EmailLog;
use App\Models\Pratica;
use App\Models\Tenant;
use App\Models\WhatsappSession;
use App\Services\AutomationPlanner;
use App\Services\TenantMailerResolver;
use App\Support\AccessoImpostazioni;
use App\Support\RegoleAutomazione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impostazioni → Automazioni: elenco ed editor grafico (innesco → messaggio) usati dall'agenzia.
 * Validazione e salvataggio sono gli stessi del superadmin (RegoleAutomazione).
 */
class AutomazioniController extends Controller
{
    public function index(): Response
    {
        $tenant = AccessoImpostazioni::tenant();
        $opzioni = $this->opzioni($tenant);
        $ultimi = EmailLog::where('tenant_id', $tenant->id)->whereNotNull('automation_id')
            ->selectRaw('automation_id, max(created_at) as ultimo, count(*) as invii')
            ->where('created_at', '>=', now()->subDays(30))->groupBy('automation_id')->get()->keyBy('automation_id');

        return Inertia::render('Impostazioni/Automazioni/Index', [
            'automazioni' => Automation::where('tenant_id', $tenant->id)->with(['status', 'documentCategories'])->orderBy('name')->get()
                ->map(fn (Automation $a) => [
                    ...RegoleAutomazione::perEditor($a),
                    'innesco' => $this->descriviInnesco($a, $opzioni),
                    'invii_30gg' => (int) ($ultimi[$a->id]->invii ?? 0),
                    'ultimo_invio' => isset($ultimi[$a->id]) ? (string) $ultimi[$a->id]->ultimo : null,
                ]),
            'opzioni' => $opzioni,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = AccessoImpostazioni::tenant();

        return Inertia::render('Impostazioni/Automazioni/Editor', [
            'automazione' => null,
            'modello' => $request->string('modello')->toString() ?: null,
            'opzioni' => $this->opzioni($tenant),
        ]);
    }

    public function edit(Automation $automazione): Response
    {
        $tenant = $this->propria($automazione);

        return Inertia::render('Impostazioni/Automazioni/Editor', [
            'automazione' => RegoleAutomazione::perEditor($automazione->load('documentCategories')),
            'modello' => null,
            'opzioni' => $this->opzioni($tenant),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        $automazione = RegoleAutomazione::salva($tenant, RegoleAutomazione::valida($request, $tenant));

        return redirect()->route('impostazioni.automazioni.edit', $automazione)->with('success', 'Automazione creata.');
    }

    public function update(Request $request, Automation $automazione): RedirectResponse
    {
        $tenant = $this->propria($automazione);
        RegoleAutomazione::salva($tenant, RegoleAutomazione::valida($request, $tenant), $automazione);

        return back()->with('success', 'Automazione salvata.');
    }

    public function toggle(Request $request, Automation $automazione): RedirectResponse
    {
        $this->propria($automazione);
        $automazione->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', $automazione->is_active ? 'Automazione accesa.' : 'Automazione spenta.');
    }

    public function destroy(Automation $automazione): RedirectResponse
    {
        $this->propria($automazione);
        $nome = $automazione->name;
        $automazione->documentCategories()->detach();
        $automazione->delete();

        return redirect()->route('impostazioni.automazioni.index')->with('success', "Automazione \"{$nome}\" eliminata.");
    }

    /** Anteprima con i dati di un sinistro (o cliente) vero, senza salvare né inviare. */
    public function anteprima(Request $request, AutomationPlanner $planner): JsonResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        [$automazione, $errori] = $this->bozza($request, $tenant);
        if ($errori) {
            return response()->json(['errori' => $errori], 422);
        }

        return response()->json(['piano' => $this->piano($request, $tenant, $automazione, $planner)]);
    }

    /** Invia l'email di prova solo a chi la sta configurando (mai ai clienti). */
    public function prova(Request $request, AutomationPlanner $planner, TenantMailerResolver $mailer): JsonResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        [$automazione, $errori] = $this->bozza($request, $tenant);
        if ($errori) {
            return response()->json(['errori' => $errori], 422);
        }

        $email = $request->user()->email;
        $piano = $this->piano($request, $tenant, $automazione, $planner);

        try {
            $mailer->send($tenant, $email, new AutomazioneNotificaMail(
                emailSubject: '[PROVA] '.$piano['subject'],
                compiledBody: $piano['message'],
                tenantName: $tenant->name,
                documentLinks: array_map(fn ($d) => ['nome_file' => $d['nome_file'], 'url' => '#'], $piano['documents'] ?? []),
                firma: \App\Models\EmailFirma::predefinitaPer($tenant),
            ), [], ['tipo' => 'test', 'automation_id' => $automazione->id ?: null]);
        } catch (\Throwable $e) {
            return response()->json(['errori' => ['prova' => 'Invio non riuscito: '.$e->getMessage().' — controlla Impostazioni → Casella email.']], 422);
        }

        return response()->json(['messaggio' => "Email di prova inviata a {$email}."]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function propria(Automation $automazione): Tenant
    {
        $tenant = AccessoImpostazioni::tenant();
        abort_unless($automazione->tenant_id === $tenant->id, 404);

        return $tenant;
    }

    /** Automazione non salvata costruita dal modulo dell'editor (o gli errori di validazione). */
    private function bozza(Request $request, Tenant $tenant): array
    {
        try {
            $dati = RegoleAutomazione::valida($request, $tenant);
        } catch (ValidationException $e) {
            return [null, collect($e->errors())->map(fn ($m) => $m[0])->all()];
        }

        $categorie = DocumentCategory::whereIn('id', $dati['document_category_ids'])->get();
        unset($dati['document_category_ids']);
        $automazione = new Automation([...$dati, 'tenant_id' => $tenant->id]);
        $automazione->id = (int) $request->input('id') ?: 0;
        $automazione->setRelation('documentCategories', $categorie);

        return [$automazione, []];
    }

    private function piano(Request $request, Tenant $tenant, Automation $automazione, AutomationPlanner $planner): array
    {
        if ($automazione->trigger_type === 'cliente_date_field') {
            $cliente = Cliente::where('tenant_id', $tenant->id)->find($request->integer('cliente_id'))
                ?? Cliente::where('tenant_id', $tenant->id)->latest()->first();
            abort_unless($cliente, 422, 'Serve almeno un cliente per l\'anteprima.');

            return $planner->planCliente($cliente, $automazione, (string) $automazione->watched_field);
        }

        $pratica = Pratica::where('tenant_id', $tenant->id)->find($request->integer('pratica_id'))
            ?? Pratica::where('tenant_id', $tenant->id)->latest()->first();
        abort_unless($pratica, 422, 'Serve almeno un sinistro per l\'anteprima.');

        $ctx = in_array($automazione->trigger_type, ['status', 'status_duration'], true) && $automazione->tenant_status_id ? ['status_id' => $automazione->tenant_status_id] : [];
        $piano = $planner->planPratica($pratica, $automazione, $ctx);
        $piano['pratica'] = ['id' => $pratica->id, 'cliente' => $pratica->cliente?->nome];

        // Dati usati nel testo ma vuoti in questo sinistro (es. perizia non ancora fissata).
        $testo = $automazione->message_template.' '.$automazione->subject;
        $etichette = AutomationPlanner::VARIABILI_PRATICA + collect($tenant->getCustomFieldsSchema())->mapWithKeys(fn ($f) => ['{campi_custom.'.$f['name'].'}' => $f['label']])->all();
        $piano['mancanti'] = collect($etichette)
            ->filter(function ($etichetta, $codice) use ($testo, $planner, $pratica) {
                if ($codice === '{nome_cliente}' || ! str_contains($testo, $codice)) {
                    return false;
                }
                $valore = trim($planner->compilePratica($codice, $pratica, null));

                // Campo personalizzato mai compilato: il segnaposto resta tale e quale.
                return $valore === '' || $valore === $codice;
            })
            ->values()->all();

        return $piano;
    }

    /** Tutto ciò che serve all'editor per i menu a tendina. */
    private function opzioni(Tenant $tenant): array
    {
        $whatsapp = WhatsappSession::where('tenant_id', $tenant->id)->where('status', 'active')->exists();

        return [
            'stati' => $tenant->statuses()->orderBy('order')->get(['id', 'name', 'color', 'is_closed'])->toArray(),
            'datePratica' => [
                ['name' => 'data_appuntamento', 'label' => 'Data perizia (sopralluogo)'],
                ...collect($tenant->getCustomFieldsSchema())->where('type', 'date')->map(fn ($f) => ['name' => $f['name'], 'label' => $f['label']])->values()->all(),
            ],
            'dateCliente' => $tenant->hasFeature('clienti')
                ? collect($tenant->getClienteCustomFieldsSchema())->where('type', 'date')->map(fn ($f) => ['name' => $f['name'], 'label' => $f['label']])->values()->all()
                : [],
            'utenti' => $tenant->users()->where('is_active', true)->where('role', '!=', 'superadmin')->orderBy('name')->get(['id', 'name', 'email', 'role'])->toArray(),
            'contatti' => Contatto::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('nome')->get(['id', 'nome', 'email', 'telefono', 'tags'])
                ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome, 'email' => $c->email, 'telefono' => $c->telefono, 'tags' => $c->tags ?? []])->all(),
            'categorie' => $tenant->categorieDocumenti()->where('is_enabled', true)->map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']])->values()->all(),
            'variabiliPratica' => collect(AutomationPlanner::VARIABILI_PRATICA)->map(fn ($l, $k) => ['codice' => $k, 'etichetta' => $l])->values()
                ->merge(collect($tenant->getCustomFieldsSchema())->map(fn ($f) => ['codice' => '{campi_custom.'.$f['name'].'}', 'etichetta' => $f['label']]))->all(),
            'variabiliCliente' => collect(AutomationPlanner::VARIABILI_CLIENTE)->map(fn ($l, $k) => ['codice' => $k, 'etichetta' => $l])->values()
                ->merge(collect($tenant->getClienteCustomFieldsSchema())->map(fn ($f) => ['codice' => '{cliente.'.$f['name'].'}', 'etichetta' => $f['label']]))->all(),
            'pratiche' => Pratica::where('tenant_id', $tenant->id)->with('cliente:id,nome')->latest()->limit(30)->get(['id', 'cliente_id', 'compagnia'])
                ->map(fn ($p) => ['id' => $p->id, 'etichetta' => "#{$p->id} — ".($p->cliente?->nome ?? 'senza cliente')])->all(),
            'clienti' => $tenant->hasFeature('clienti')
                ? Cliente::where('tenant_id', $tenant->id)->latest()->limit(30)->get(['id', 'nome'])->map(fn ($c) => ['id' => $c->id, 'etichetta' => $c->nome])->all()
                : [],
            'emailCollegata' => (bool) $tenant->mailSettings?->is_active,
            'whatsappCollegato' => $whatsapp,
            'templatesWhatsapp' => \App\Models\WhatsappTemplate::where('tenant_id', $tenant->id)->whereIn('status', ['APPROVED', 'PENDING'])->orderBy('name')->get()->map->perUi()->values()->all(),
            'inneschi' => collect(Automation::TRIGGER)
                ->reject(fn ($l, $k) => $k === 'cliente_date_field' && ! $tenant->hasFeature('clienti'))
                ->map(fn ($l, $k) => ['valore' => $k, 'etichetta' => $l])->values()->all(),
        ];
    }

    /** Frase dell'innesco per l'elenco, es. "Quando lo stato diventa Perizia". */
    private function descriviInnesco(Automation $a, array $opzioni): string
    {
        $data = fn (array $lista) => collect($lista)->firstWhere('name', $a->watched_field)['label'] ?? $a->watched_field;

        return match ($a->trigger_type) {
            'status' => $a->status ? "Quando lo stato diventa «{$a->status->name}»" : 'A ogni cambio di stato',
            'pratica_created' => 'Quando si apre un nuovo sinistro',
            'date_field' => 'Quando cambia «'.$data($opzioni['datePratica']).'»',
            'date_reminder' => $this->giorni($a->offset_days).' «'.$data($opzioni['datePratica']).'»',
            'status_duration' => "Fermo da {$a->offset_days} ".($a->offset_days === 1 ? 'giorno' : 'giorni').' in «'.($a->status?->name ?? '?').'»'.($a->ripeti ? ', ripetuto' : ''),
            'cliente_date_field' => ($a->days_before ? "{$a->days_before} giorni prima di" : 'Il giorno di').' «'.$data($opzioni['dateCliente']).'» del cliente',
            default => $a->trigger_type,
        };
    }

    private function giorni(int $offset): string
    {
        return match (true) {
            $offset === 0 => 'Il giorno di',
            $offset < 0 => abs($offset).' '.(abs($offset) === 1 ? 'giorno' : 'giorni').' prima di',
            default => $offset.' '.($offset === 1 ? 'giorno' : 'giorni').' dopo',
        };
    }
}
