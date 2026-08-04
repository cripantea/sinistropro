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
        $data = $this->validated($request, $tenant);

        $automation = Automation::create([
            'tenant_id'             => $tenant->id,
            'name'                  => $data['name'],
            'trigger_type'          => $data['trigger_type'],
            'tenant_status_id'      => $data['trigger_type'] === 'status' ? ($data['tenant_status_id'] ?? null) : null,
            'watched_field'         => $data['trigger_type'] === 'date_field' ? $data['watched_field'] : null,
            'channel'               => $data['channel'],
            'recipient'             => $data['recipient'] ?? 'cliente',
            'recipients_to'         => $data['recipients_to'] ?? null,
            'recipients_cc'         => $data['recipients_cc'] ?? null,
            'message_template'      => $data['message_template'],
            'is_active'             => $data['is_active'] ?? true,
            'requires_confirmation' => $data['requires_confirmation'] ?? false,
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

        $data = $this->validated($request, $tenant);

        $automation->update([
            'name'                  => $data['name'],
            'trigger_type'          => $data['trigger_type'],
            'tenant_status_id'      => $data['trigger_type'] === 'status' ? ($data['tenant_status_id'] ?? null) : null,
            'watched_field'         => $data['trigger_type'] === 'date_field' ? $data['watched_field'] : null,
            'channel'               => $data['channel'],
            'recipient'             => $data['recipient'] ?? $automation->recipient,
            'recipients_to'         => $data['recipients_to'] ?? null,
            'recipients_cc'         => $data['recipients_cc'] ?? null,
            'message_template'      => $data['message_template'],
            'is_active'             => $data['is_active'] ?? $automation->is_active,
            'requires_confirmation' => $data['requires_confirmation'] ?? false,
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

    /**
     * @return array{
     *     name: string, trigger_type: string, tenant_status_id: ?int, watched_field: ?string,
     *     channel: string, recipient: string, message_template: string,
     *     document_category_ids: ?array, is_active: ?bool, requires_confirmation: ?bool
     * }
     */
    private function validated(Request $request, Tenant $tenant): array
    {
        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'trigger_type'            => ['required', Rule::in(['status', 'date_field'])],
            'tenant_status_id'        => ['nullable', 'integer'],
            'watched_field'           => ['nullable', 'string', 'required_if:trigger_type,date_field'],
            'channel'                    => ['required', Rule::in(['email', 'whatsapp', 'both'])],
            'recipient'                  => ['nullable', Rule::in(['cliente', 'perito', 'gestore'])],
            'recipients_to'              => ['nullable', 'array'],
            'recipients_to.*.type'       => ['required', Rule::in(['cliente', 'carrozzeria', 'user'])],
            'recipients_to.*.user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'recipients_cc'              => ['nullable', 'array'],
            'recipients_cc.*.type'       => ['required', Rule::in(['user'])],
            'recipients_cc.*.user_id'    => ['required', 'integer', 'exists:users,id'],
            'message_template'           => ['required', 'string'],
            'document_category_ids'      => ['nullable', 'array'],
            'document_category_ids.*'    => ['integer', 'exists:document_categories,id'],
            'is_active'                  => ['boolean'],
            'requires_confirmation'      => ['boolean'],
        ]);

        if ($data['trigger_type'] === 'status' && ! empty($data['tenant_status_id'])) {
            abort_unless(
                TenantStatus::where('id', $data['tenant_status_id'])
                    ->where('tenant_id', $tenant->id)
                    ->exists(),
                422,
                'Stato non valido per questo tenant.'
            );
        }

        if ($data['trigger_type'] === 'date_field') {
            $dateCustomFields = collect($tenant->getCustomFieldsSchema())
                ->where('type', 'date')
                ->pluck('name')
                ->all();

            abort_unless(
                in_array($data['watched_field'], array_merge(['data_appuntamento'], $dateCustomFields), true),
                422,
                'Campo data non valido per questo tenant.'
            );
        }

        return $data;
    }
}
