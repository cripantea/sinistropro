<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;

class TenantContextController extends Controller
{
    /** POST /superadmin/tenants/{tenant}/context — entra nel tenant restando superadmin */
    public function set(Tenant $tenant): RedirectResponse
    {
        TenantContext::set($tenant->id);

        return redirect()
            ->route('dashboard')
            ->with('success', "Stai navigando il tenant \"{$tenant->name}\".");
    }

    /** POST /superadmin/tenant-context/clear — torna al pannello superadmin */
    public function clear(): RedirectResponse
    {
        TenantContext::clear();

        return redirect()->route('superadmin.dashboard');
    }
}
