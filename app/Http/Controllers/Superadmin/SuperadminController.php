<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Pratica;
use App\Models\Tenant;
use App\Models\User;
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
        // withCount su Tenant per avere i totali per riga senza N+1.
        $recentTenants = Tenant::withCount(['users', 'pratiche'])
            ->latest()
            ->take(6)
            ->get();

        return Inertia::render('Superadmin/Dashboard', [
            'stats' => [
                'tenants'  => Tenant::count(),
                'users'    => User::count(),
                'pratiche' => Pratica::acrossAllTenants()->count(),
            ],
            'recentTenants' => $recentTenants,
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

        return redirect()->route('superadmin.users')
            ->with('success', "L'utente {$request->name} è stato creato con successo.");
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

        return redirect()->route('superadmin.users')
            ->with('success', "L'utente {$user->name} è stato aggiornato con successo.");
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
