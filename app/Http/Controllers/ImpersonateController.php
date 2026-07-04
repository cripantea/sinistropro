<?php

namespace App\Http\Controllers;

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
