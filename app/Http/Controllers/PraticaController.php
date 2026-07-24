<?php

namespace App\Http\Controllers;

use App\Events\PraticaStatoAggiornato;
use App\Http\Requests\Pratica\StorePraticaRequest;
use App\Http\Requests\Pratica\UpdatePraticaRequest;
use App\Mail\PraticaStatoAggiornatoMail;
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
        $statuses = $user->tenant?->statuses ?? collect();

        $pratiche = Pratica::with(['currentStatus', 'utenteCreatore:id,name'])
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where(function (Builder $q) use ($request): void {
                    $term = '%' . $request->search . '%';
                    $q->where('id', 'like', $term)
                      ->orWhereRaw('CAST(custom_fields AS CHAR) LIKE ?', [$term]);
                })
            )
            ->when(
                $request->filled('status_id'),
                fn (Builder $q) => $q->where('current_status_id', $request->integer('status_id'))
            )
            ->orderByDesc('data_prossimo_avviso')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Pratiche/Index', [
            'pratiche' => $pratiche,
            'statuses' => $statuses,
            'filters'  => $request->only(['search', 'status_id']),
        ]);
    }

    public function kanban(): Response
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        abort_unless($tenant !== null, 403, 'Nessun tenant associato a questo account.');

        $statuses = $tenant->statuses;

        $pratiche = Pratica::orderByDesc('data_prossimo_avviso')
            ->get(['id', 'current_status_id', 'data_prossimo_avviso', 'custom_fields']);

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

        $pratica->update([
            'current_status_id' => $request->current_status_id,
            'custom_fields'     => $customFields ?: null,
        ]);

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
            event(new PraticaStatoAggiornato($pratica, $oldStatusId, $newStatusId));
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
