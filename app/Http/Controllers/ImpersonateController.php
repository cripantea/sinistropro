<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    /**
     * POST /superadmin/impersonate/{user}
     * Accessibile solo dai Superadmin (middleware 'superadmin').
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        // Impedisce di impersonare un altro superadmin.
        if ($user->role === 'superadmin') {
            abort(403, 'Non è possibile impersonare un altro Superadmin.');
        }

        // Salva l'identità del superadmin originale nella sessione.
        session(['impersonator_id' => auth()->id()]);

        auth()->loginUsingId($user->id);

        return redirect()
            ->route('dashboard')
            ->with('success', "Stai operando come {$user->name}.");
    }

    /**
     * POST /superadmin/tenants/{tenant}/enter
     * Entra nel tenant impersonando il primo admin disponibile.
     */
    public function enterTenant(Tenant $tenant): RedirectResponse
    {
        $admin = User::where('tenant_id', $tenant->id)
            ->whereIn('role', ['tenant-admin', 'user'])
            ->where('is_active', true)
            ->orderByRaw("FIELD(role, 'tenant-admin', 'user')")
            ->first();

        if (! $admin) {
            return redirect()
                ->route('superadmin.tenants.index')
                ->with('error', "Il tenant \"{$tenant->name}\" non ha utenti attivi.");
        }

        session(['impersonator_id' => auth()->id()]);
        auth()->loginUsingId($admin->id);

        return redirect()
            ->route('dashboard')
            ->with('success', "Stai operando nel tenant \"{$tenant->name}\".");
    }

    /**
     * POST /impersonate/leave
     * Termina l'impersonazione e ripristina il superadmin.
     */
    public function leave(Request $request): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        if (! $impersonatorId) {
            abort(403, 'Nessuna sessione di impersonazione attiva.');
        }

        $impersonator = User::findOrFail($impersonatorId);

        session()->forget('impersonator_id');

        auth()->loginUsingId($impersonator->id);

        return redirect()
            ->route('superadmin.dashboard')
            ->with('success', 'Impersonazione terminata. Sei tornato al pannello Superadmin.');
    }
}
