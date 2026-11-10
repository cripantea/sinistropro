<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Support\AccessoImpostazioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Impostazioni → Generale: nome dell'agenzia e giorni di avviso predefiniti. */
class GeneraleController extends Controller
{
    public function show(): Response
    {
        $tenant = AccessoImpostazioni::tenant();

        return Inertia::render('Impostazioni/Generale', [
            'agenzia' => ['name' => $tenant->name, 'default_notice_days' => $tenant->getDefaultNoticeDays()],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = AccessoImpostazioni::tenant();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'default_notice_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], ['name.unique' => 'Esiste già un\'agenzia con questo nome.']);

        $tenant->update([
            'name' => $data['name'],
            'settings' => [...($tenant->settings ?? []), 'default_notice_days' => (int) $data['default_notice_days']],
        ]);

        return back()->with('success', 'Impostazioni salvate.');
    }
}
