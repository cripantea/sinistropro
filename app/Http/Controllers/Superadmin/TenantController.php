<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\StoreTenantRequest;
use App\Http\Requests\Superadmin\UpdateTenantRequest;
use App\Models\Automation;
use App\Models\DocumentCategory;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::withCount(['users', 'pratiche', 'statuses'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Superadmin/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Superadmin/Tenants/Create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = DB::transaction(function () use ($request): Tenant {
            $tenant = Tenant::create([
                'name'     => $request->name,
                'settings' => [
                    'default_notice_days'  => $request->integer('default_notice_days'),
                    'custom_fields_schema' => $request->input('custom_fields_schema', []),
                ],
            ]);

            foreach ($request->input('statuses', []) as $i => $statusData) {
                $tenant->statuses()->create([
                    'name'      => $statusData['name'],
                    'color'     => $statusData['color'],
                    'is_closed' => (bool) ($statusData['is_closed'] ?? false),
                    'order'     => $i,
                ]);
            }

            return $tenant;
        });

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" creato con successo.");
    }

    public function edit(Tenant $tenant): Response
    {
        $tenant->load('statuses');

        $allCats   = DocumentCategory::orderBy('name')->get(['id', 'name', 'description']);
        $pivotRows = DB::table('tenant_document_categories')
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('document_category_id');

        $categoriesConfig = $allCats->map(fn ($cat) => [
            'id'              => $cat->id,
            'name'            => $cat->name,
            'description'     => $cat->description,
            'is_enabled'      => $pivotRows->has($cat->id) ? (bool) $pivotRows[$cat->id]->is_enabled : true,
            'max_file_size_mb' => $pivotRows->has($cat->id) ? (int) $pivotRows[$cat->id]->max_file_size_mb : 50,
        ])->values();

        $automations = Automation::where('tenant_id', $tenant->id)
            ->with(['status:id,name,color', 'documentCategories:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id'                    => $a->id,
                'name'                  => $a->name,
                'tenant_status_id'      => $a->tenant_status_id,
                'channel'               => $a->channel,
                'recipient'             => $a->recipient,
                'message_template'      => $a->message_template,
                'is_active'             => $a->is_active,
                'document_category_ids' => $a->documentCategories->pluck('id')->all(),
                'status'                => $a->status
                    ? ['id' => $a->status->id, 'name' => $a->status->name, 'color' => $a->status->color]
                    : null,
            ])
            ->values();

        $allDocCategories = DocumentCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Superadmin/Tenants/Edit', [
            'tenant'            => $tenant,
            'categoriesConfig'  => $categoriesConfig,
            'automations'       => $automations,
            'allDocCategories'  => $allDocCategories,
        ]);
    }

    public function syncDocumentCategories(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'categories'                  => ['required', 'array'],
            'categories.*.id'             => ['required', 'integer', 'exists:document_categories,id'],
            'categories.*.is_enabled'     => ['required', 'boolean'],
            'categories.*.max_file_size_mb' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $syncPayload = [];
        foreach ($data['categories'] as $cat) {
            $syncPayload[$cat['id']] = [
                'is_enabled'       => $cat['is_enabled'],
                'max_file_size_mb' => $cat['max_file_size_mb'],
            ];
        }

        $tenant->documentCategories()->sync($syncPayload);

        return redirect()->route('superadmin.tenants.edit', $tenant)
            ->with('success', 'Configurazione categorie salvata.');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        DB::transaction(function () use ($request, $tenant): void {
            $tenant->update([
                'name'     => $request->name,
                'settings' => [
                    'default_notice_days'  => $request->integer('default_notice_days'),
                    'custom_fields_schema' => $request->input('custom_fields_schema', []),
                ],
            ]);

            // Sincronizza gli statuses: upsert per quelli con ID, crea i nuovi.
            $incomingIds = collect($request->input('statuses', []))
                ->filter(fn ($s) => ! empty($s['id']))
                ->pluck('id')
                ->all();

            // Elimina gli statuses rimossi dall'utente.
            $tenant->statuses()->whereNotIn('id', $incomingIds)->delete();

            foreach ($request->input('statuses', []) as $i => $statusData) {
                $tenant->statuses()->updateOrCreate(
                    ['id' => $statusData['id'] ?? null],
                    [
                        'name'      => $statusData['name'],
                        'color'     => $statusData['color'],
                        'is_closed' => (bool) ($statusData['is_closed'] ?? false),
                        'order'     => $i,
                    ]
                );
            }
        });

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" aggiornato.");
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $name = $tenant->name;
        $tenant->delete();

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', "Tenant \"{$name}\" eliminato.");
    }
}
