<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Tenant;
use App\Models\TenantStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomationController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'tenant_status_id'        => ['nullable', 'integer'],
            'channel'                 => ['required', Rule::in(['email', 'whatsapp', 'both'])],
            'recipient'               => ['required', Rule::in(['cliente', 'perito', 'gestore'])],
            'message_template'        => ['required', 'string'],
            'document_category_ids'   => ['nullable', 'array'],
            'document_category_ids.*' => ['integer', 'exists:document_categories,id'],
            'is_active'               => ['boolean'],
        ]);

        if (! empty($data['tenant_status_id'])) {
            abort_unless(
                TenantStatus::where('id', $data['tenant_status_id'])
                    ->where('tenant_id', $tenant->id)
                    ->exists(),
                422,
                'Stato non valido per questo tenant.'
            );
        }

        $automation = Automation::create([
            'tenant_id'        => $tenant->id,
            'name'             => $data['name'],
            'tenant_status_id' => $data['tenant_status_id'] ?? null,
            'channel'          => $data['channel'],
            'recipient'        => $data['recipient'],
            'message_template' => $data['message_template'],
            'is_active'        => $data['is_active'] ?? true,
        ]);

        $automation->documentCategories()->sync($data['document_category_ids'] ?? []);

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant) . '?tab=automations')
            ->with('success', "Automazione \"{$automation->name}\" creata.");
    }

    public function update(Request $request, Tenant $tenant, Automation $automation): RedirectResponse
    {
        abort_unless($automation->tenant_id === $tenant->id, 403);

        // Toggle rapido da tabella: payload con solo is_active
        if (array_keys($request->all()) === ['is_active']) {
            $automation->update(['is_active' => $request->boolean('is_active')]);

            return redirect()
                ->to(route('superadmin.tenants.edit', $tenant) . '?tab=automations')
                ->with('success', 'Stato automazione aggiornato.');
        }

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'tenant_status_id'        => ['nullable', 'integer'],
            'channel'                 => ['required', Rule::in(['email', 'whatsapp', 'both'])],
            'recipient'               => ['required', Rule::in(['cliente', 'perito', 'gestore'])],
            'message_template'        => ['required', 'string'],
            'document_category_ids'   => ['nullable', 'array'],
            'document_category_ids.*' => ['integer', 'exists:document_categories,id'],
            'is_active'               => ['boolean'],
        ]);

        if (! empty($data['tenant_status_id'])) {
            abort_unless(
                TenantStatus::where('id', $data['tenant_status_id'])
                    ->where('tenant_id', $tenant->id)
                    ->exists(),
                422,
                'Stato non valido per questo tenant.'
            );
        }

        $automation->update([
            'name'             => $data['name'],
            'tenant_status_id' => $data['tenant_status_id'] ?? null,
            'channel'          => $data['channel'],
            'recipient'        => $data['recipient'],
            'message_template' => $data['message_template'],
            'is_active'        => $data['is_active'] ?? $automation->is_active,
        ]);

        $automation->documentCategories()->sync($data['document_category_ids'] ?? []);

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant) . '?tab=automations')
            ->with('success', "Automazione \"{$automation->name}\" aggiornata.");
    }

    public function destroy(Tenant $tenant, Automation $automation): RedirectResponse
    {
        abort_unless($automation->tenant_id === $tenant->id, 403);

        $name = $automation->name;
        $automation->documentCategories()->detach();
        $automation->delete();

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant) . '?tab=automations')
            ->with('success', "Automazione \"{$name}\" eliminata.");
    }
}
