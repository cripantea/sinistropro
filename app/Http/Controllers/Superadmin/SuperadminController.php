<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CruscottoSuperadmin;
use App\Support\Impostazioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Superadmin/Dashboard', CruscottoSuperadmin::dati());
    }

    /** Obiettivo di incasso mensile (in euro, IVA inclusa) mostrato in dashboard. */
    public function obiettivo(Request $request): RedirectResponse
    {
        $data = $request->validate(['euro' => ['required', 'numeric', 'min:0', 'max:10000000']]);

        Impostazioni::set('obiettivo_mensile_cents', (string) (int) round($data['euro'] * 100));

        return back()->with('success', 'Obiettivo aggiornato.');
    }

    public function auditLogs(Request $request): Response
    {
        $query = AuditLog::with(['user:id,name,email', 'impersonator:id,name,email', 'tenant:id,name'])
            ->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        if ($request->filled('model')) {
            $query->where('auditable_type', 'like', '%' . $request->input('model') . '%');
        }

        $logs    = $query->paginate(50)->withQueryString();
        $tenants = Tenant::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Superadmin/AuditLogs', [
            'logs'    => $logs,
            'tenants' => $tenants,
            'filters' => $request->only(['action', 'tenant_id', 'model']),
        ]);
    }

    public function users(Request $request): Response
    {
        $users = User::with('tenant:id,name')
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where(
                    fn ($q) => $q
                        ->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->filled('role'),      fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->integer('tenant_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $tenants = Tenant::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Superadmin/Users', [
            'users'   => $users,
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'role', 'tenant_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'role'      => ['required', Rule::in(['superadmin', 'tenant-admin', 'user', 'external'])],
            'tenant_id' => ['required_unless:role,superadmin', 'nullable', 'exists:tenants,id'],
        ]);

        User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'email_verified_at' => now(),
            'password'          => Hash::make($request->password),
            'role'              => $request->role,
            'tenant_id'         => $request->role === 'superadmin' ? null : $request->tenant_id,
            'is_active'         => true,
        ]);

        // back(): si torna alla lista utenti o alla sezione Utenti del tenant, da dove si è partiti.
        return back()->with('success', "L'utente {$request->name} è stato creato con successo.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'  => ['nullable', 'string', 'min:8'],
            'role'      => ['required', Rule::in(['superadmin', 'tenant-admin', 'user', 'external'])],
            'tenant_id' => ['required_unless:role,superadmin', 'nullable', 'exists:tenants,id'],
        ]);

        $data = [
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'tenant_id' => $request->role === 'superadmin' ? null : $request->tenant_id,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return back()->with('success', "L'utente {$user->name} è stato aggiornato con successo.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->role === 'superadmin') {
            abort(403, 'Non è possibile disabilitare un Superadmin.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        $stato = $user->is_active ? 'attivato' : 'disabilitato';

        return redirect()->back()
            ->with('success', "L'utente {$user->name} è stato {$stato}.");
    }
}
