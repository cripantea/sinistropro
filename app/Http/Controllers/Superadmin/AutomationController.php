<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\RegoleAutomazione;

class AutomationController extends Controller
{
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $automation = RegoleAutomazione::salva($tenant, RegoleAutomazione::valida($request, $tenant));

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

        RegoleAutomazione::salva($tenant, RegoleAutomazione::valida($request, $tenant), $automation);

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
