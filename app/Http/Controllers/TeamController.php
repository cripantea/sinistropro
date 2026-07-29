<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403, 'Solo il tenant-admin può gestire il team.');

        $members = User::where('tenant_id', $authed->tenant_id)
            ->where('role', '!=', 'superadmin')
            ->orderByRaw("FIELD(role, 'tenant-admin', 'user', 'external')")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'external_type', 'is_active', 'created_at']);

        return Inertia::render('Team/Index', [
            'members' => $members,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8'],
            'role'          => ['required', Rule::in(['tenant-admin', 'user', 'external'])],
            'external_type' => ['nullable', Rule::in(['perito', 'carrozzeria'])],
        ]);

        User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'role'              => $data['role'],
            'external_type'     => $data['role'] === 'external' ? ($data['external_type'] ?? 'perito') : null,
            'tenant_id'         => $authed->tenant_id,
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        return redirect()->route('team.index')
            ->with('success', "Collaboratore {$data['name']} aggiunto al team.");
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403);
        abort_unless($member->tenant_id === $authed->tenant_id, 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', Rule::unique('users')->ignore($member->id)],
            'password'      => ['nullable', 'string', 'min:8'],
            'role'          => ['required', Rule::in(['tenant-admin', 'user', 'external'])],
            'external_type' => ['nullable', Rule::in(['perito', 'carrozzeria'])],
        ]);

        $updates = [
            'name'          => $data['name'],
            'email'         => $data['email'],
            'role'          => $data['role'],
            'external_type' => $data['role'] === 'external' ? ($data['external_type'] ?? 'perito') : null,
        ];

        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        $member->update($updates);

        return redirect()->route('team.index')
            ->with('success', "{$member->name} aggiornato.");
    }

    public function toggleActive(User $member): RedirectResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403);
        abort_unless($member->tenant_id === $authed->tenant_id, 403);
        abort_if($member->id === $authed->id, 403, 'Non puoi disattivare il tuo stesso account.');

        $member->update(['is_active' => ! $member->is_active]);
        $label = $member->is_active ? 'attivato' : 'disattivato';

        return redirect()->back()->with('success', "{$member->name} {$label}.");
    }
}
