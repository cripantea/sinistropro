<?php

namespace App\Http\Controllers;

use App\Events\PraticaCampoDataAggiornato;
use App\Events\PraticaStatoAggiornato;
use App\Http\Requests\Pratica\StorePraticaRequest;
use App\Http\Requests\Pratica\UpdatePraticaRequest;
use App\Mail\PraticaStatoAggiornatoMail;
use App\Models\Cliente;
use App\Models\DocumentCategory;
use App\Models\FieldDictionaryEntry;
use App\Models\Ispezione;
use App\Models\ModuleTemplate;
use App\Models\Pratica;
use App\Models\PraticaModule;
use App\Models\TenantStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PraticaController extends Controller
{
    public function index(Request $request): Response
    {
        $user     = auth()->user();
        $tenant   = $user->tenant;
        $statuses = $tenant?->statuses ?? collect();

        $customFieldsSchema = $tenant?->getCustomFieldsSchema() ?? [];
        $customFieldNames   = collect($customFieldsSchema)->pluck('name')->all();

        // Ordinamento: colonne fisse oppure un campo personalizzato del tenant (whitelist,
        // per evitare che un valore arbitrario finisca in un path JSON della query).
        $sortableColumns = ['id', 'cliente', 'current_status_id', 'data_prossimo_avviso', 'utente_creatore', 'created_at'];
        $sortField         = (string) $request->input('sort_field', 'data_prossimo_avviso');
        $sortDir           = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';
        $isCustomFieldSort = in_array($sortField, $customFieldNames, true);
        if (! $isCustomFieldSort && ! in_array($sortField, $sortableColumns, true)) {
            $sortField = 'data_prossimo_avviso';
        }

        // Filtro per campo personalizzato: stessa whitelist del sort.
        $filterField       = $request->input('filter_field');
        $filterValue       = $request->input('filter_value');
        $filterFieldSchema = collect($customFieldsSchema)->firstWhere('name', $filterField);

        $pratiche = Pratica::with(['currentStatus', 'utenteCreatore:id,name', 'cliente:id,nome'])
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where(function (Builder $q) use ($request): void {
                    $term = '%' . $request->search . '%';
                    $q->where('id', 'like', $term)
                      ->orWhereRaw('CAST(custom_fields AS CHAR) LIKE ?', [$term])
                      ->orWhereHas('cliente', fn (Builder $c) => $c->where('nome', 'like', $term));
                })
            )
            ->when(
                $request->filled('status_id'),
                fn (Builder $q) => $q->where('current_status_id', $request->integer('status_id'))
            )
            ->when(
                $filterFieldSchema && $filterValue !== null && $filterValue !== '',
                function (Builder $q) use ($filterFieldSchema, $filterValue): void {
                    $key = "custom_fields->{$filterFieldSchema['name']}";
                    match ($filterFieldSchema['type']) {
                        'boolean'         => $q->where($key, '=', filter_var($filterValue, FILTER_VALIDATE_BOOLEAN)),
                        'date', 'number'  => $q->where($key, '=', $filterValue),
                        default           => $q->where($key, 'like', '%' . $filterValue . '%'),
                    };
                }
            )
            ->when(
                $isCustomFieldSort,
                fn (Builder $q) => $q->orderBy("custom_fields->{$sortField}", $sortDir)
            )
            ->when(
                ! $isCustomFieldSort && $sortField === 'cliente',
                fn (Builder $q) => $q->orderBy(Cliente::select('nome')->whereColumn('clienti.id', 'pratiche.cliente_id'), $sortDir)
            )
            ->when(
                ! $isCustomFieldSort && $sortField === 'current_status_id',
                fn (Builder $q) => $q->orderBy(TenantStatus::select('order')->whereColumn('tenant_statuses.id', 'pratiche.current_status_id'), $sortDir)
            )
            ->when(
                ! $isCustomFieldSort && $sortField === 'utente_creatore',
                fn (Builder $q) => $q->orderBy(User::select('name')->whereColumn('users.id', 'pratiche.utente_creatore_id'), $sortDir)
            )
            ->when(
                ! $isCustomFieldSort && in_array($sortField, ['id', 'data_prossimo_avviso', 'created_at'], true),
                fn (Builder $q) => $q->orderBy($sortField, $sortDir)
            )
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Pratiche/Index', [
            'pratiche'           => $pratiche,
            'statuses'           => $statuses,
            'customFieldsSchema' => $customFieldsSchema,
            'filters'            => $request->only(['search', 'status_id', 'sort_field', 'sort_dir', 'filter_field', 'filter_value']),
        ]);
    }

    public function kanban(): Response
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        abort_unless($tenant !== null, 403, 'Nessun tenant associato a questo account.');

        $statuses = $tenant->statuses;

        $pratiche = Pratica::with('cliente:id,nome')
            ->orderByDesc('data_prossimo_avviso')
            ->get(['id', 'cliente_id', 'current_status_id', 'data_prossimo_avviso', 'custom_fields']);

        $externalUsers = User::where('tenant_id', $user->tenant_id)
            ->where('role', 'external')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Pratiche/Kanban', [
            'statuses'      => $statuses,
            'pratiche'      => $pratiche,
            'schema'        => $tenant->getCustomFieldsSchema(),
            'externalUsers' => $externalUsers,
        ]);
    }

    public function create(): Response
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        abort_unless($tenant !== null, 403, 'Nessun tenant associato a questo account.');

        return Inertia::render('Pratiche/Create', [
            'clienti' => $tenant->clienti()->orderBy('nome')->get(['id', 'nome']),
            'periti'  => User::where('tenant_id', $tenant->id)->where('role', 'external')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePraticaRequest $request): RedirectResponse
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        $statoIniziale = $tenant->initialStatus();
        abort_unless($statoIniziale !== null, 422, 'Nessuno stato iniziale configurato per questo tenant. Contatta l\'amministratore della piattaforma.');

        $pratica = DB::transaction(function () use ($request, $user, $tenant, $statoIniziale): Pratica {
            $pratica = Pratica::create([
                'tenant_id'            => $tenant->id,
                'utente_creatore_id'   => $user->id,
                'cliente_id'           => $request->integer('cliente_id'),
                'current_status_id'    => $statoIniziale->id,
                'data_prossimo_avviso' => now()->addDays($tenant->getDefaultNoticeDays())->toDateString(),
            ]);

            Ispezione::create([
                'tenant_id'           => $tenant->id,
                'pratica_id'          => $pratica->id,
                'assegnato_a_user_id' => $request->filled('perito_user_id') ? $request->integer('perito_user_id') : null,
                'stato'               => 'pianificata',
            ]);

            return $pratica;
        });

        return redirect()
            ->route('pratiche.show', $pratica)
            ->with('success', "Sinistro #{$pratica->id} creato con successo.");
    }

    public function show(Pratica $pratica): Response
    {
        $pratica->load([
            'tenant.statuses',
            'utenteCreatore:id,name,email',
            'currentStatus',
            'cliente:id,nome,telefono,email',
            'note.user:id,name',
            'allegati.category:id,name',
            'ispezioni.assegnatoa:id,name,email',
        ]);

        $pratica->logView();

        $tenantId  = auth()->user()->tenant_id;
        $allCats   = DocumentCategory::orderBy('name')->get(['id', 'name']);
        $pivotRows = DB::table('tenant_document_categories')
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('document_category_id');

        $enabledCategories = $allCats
            ->filter(fn ($cat) => ! $pivotRows->has($cat->id) || (bool) $pivotRows[$cat->id]->is_enabled)
            ->map(fn ($cat) => [
                'id'               => $cat->id,
                'name'             => $cat->name,
                'max_file_size_mb' => $pivotRows->has($cat->id) ? (int) $pivotRows[$cat->id]->max_file_size_mb : 50,
            ])
            ->values();

        $moduleTemplates = ModuleTemplate::orderBy('name')
            ->get(['id', 'name', 'fields_schema', 'pdf_template_s3_key', 'output_document_category_id']);

        $praticaModules = PraticaModule::where('pratica_id', $pratica->id)
            ->get(['id', 'module_template_id', 'values']);

        $externalUsers = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('role', 'external')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $fieldDictionary = FieldDictionaryEntry::where('tenant_id', $tenantId)
            ->get(['key', 'source_type', 'source_field']);

        return Inertia::render('Pratiche/Show', [
            'pratica'         => $pratica,
            'categories'      => $enabledCategories,
            'moduleTemplates' => $moduleTemplates,
            'praticaModules'  => $praticaModules,
            'externalUsers'   => $externalUsers,
            'fieldDictionary' => $fieldDictionary,
        ]);
    }

    public function edit(Pratica $pratica): Response
    {
        $pratica->load('currentStatus');

        $tenant = auth()->user()->tenant?->load('statuses');

        return Inertia::render('Pratiche/Edit', [
            'pratica' => $pratica,
            'tenant'  => $tenant,
        ]);
    }

    public function update(UpdatePraticaRequest $request, Pratica $pratica): RedirectResponse
    {
        $tenant       = auth()->user()->tenant;
        $schema       = $tenant->getCustomFieldsSchema();
        $customFields = [];
        foreach ($schema as $field) {
            $key = $field['name'];
            if ($request->has("custom_fields.{$key}")) {
                $customFields[$key] = $request->input("custom_fields.{$key}");
            }
        }

        $oldStatusId    = $pratica->current_status_id;
        $oldCustomFields = $pratica->custom_fields ?? [];
        $newStatusId    = $request->current_status_id;
        $skip           = $request->boolean('skip_confirmable_automations', false);

        $pratica->update([
            'current_status_id' => $newStatusId,
            'custom_fields'     => $customFields ?: null,
        ]);

        // Event-driven: lancia il sistema Automazioni se lo stato è cambiato.
        if ($newStatusId && $newStatusId != $oldStatusId) {
            event(new PraticaStatoAggiornato($pratica, $oldStatusId, $newStatusId, $skip));
        }

        // Event-driven: lancia il sistema Automazioni per ogni campo data osservato che è cambiato.
        foreach ($schema as $field) {
            if ($field['type'] !== 'date') {
                continue;
            }
            $key = $field['name'];
            $old = (string) ($oldCustomFields[$key] ?? '');
            $new = (string) ($customFields[$key] ?? '');
            if ($old !== $new) {
                event(new PraticaCampoDataAggiornato($pratica, $key, $skip));
            }
        }

        return redirect()
            ->route('pratiche.show', $pratica)
            ->with('success', 'Pratica aggiornata.');
    }

    /**
     * PATCH /pratiche/{pratica}/status
     * Cambio rapido di stato dalla pagina di dettaglio e dal Kanban.
     *
     * Sicurezza multi-tenant:
     *  1. Route model binding su Pratica rispetta BelongsToTenant → 404 se pratica di altro tenant.
     *  2. La closure di validazione verifica che current_status_id appartenga allo stesso tenant.
     */
    public function updateStatus(Request $request, Pratica $pratica): RedirectResponse|JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $request->validate([
            'current_status_id' => [
                'nullable',
                'exists:tenant_statuses,id',
                function ($attr, $value, $fail) use ($tenantId): void {
                    if ($value && ! \App\Models\TenantStatus::where('id', $value)->where('tenant_id', $tenantId)->exists()) {
                        $fail('Stato non valido per questo tenant.');
                    }
                },
            ],
        ]);

        $oldStatusId = $pratica->current_status_id;
        $newStatusId = $request->integer('current_status_id') ?: null;
        $skip        = $request->boolean('skip_confirmable_automations', false);

        $pratica->update(['current_status_id' => $newStatusId]);

        // Legacy: notifica email diretta tramite flag send_email_notification sullo stato
        if ($newStatusId) {
            $stato = TenantStatus::find($newStatusId);
            if ($stato && $stato->send_email_notification) {
                $fields = $pratica->custom_fields ?? [];
                $emailCliente = $fields['email'] ?? $fields['email_cliente'] ?? $fields['email_contatto'] ?? null;
                if ($emailCliente && filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
                    $pratica->load('tenant');
                    Mail::to($emailCliente)->queue(new PraticaStatoAggiornatoMail($pratica, $stato, $emailCliente));
                }
            }
        }

        // Event-driven: lancia il sistema Automazioni per il nuovo stato
        if ($newStatusId) {
            event(new PraticaStatoAggiornato($pratica, $oldStatusId, $newStatusId, $skip));
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->back()
            ->with('success', 'Stato aggiornato.');
    }

    public function destroy(Pratica $pratica): RedirectResponse
    {
        $pratica->delete();

        return redirect()
            ->route('pratiche.index')
            ->with('success', 'Pratica eliminata.');
    }
}
