<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Primo accesso con password provvisoria: l'utente ne sceglie una sua (vedi EnsurePasswordCambiata). */
class ScegliPasswordController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ScegliPassword', ['nome' => $request->user()->name]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Senza la password attuale si cambia solo la password provvisoria, e mai impersonando.
        abort_unless($request->user()->must_change_password && ! $request->session()->has('impersonator_id'), 403);

        $data = $request->validate(['password' => ['required', Password::min(8)]]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        // Agenzia non ancora pagata: si va dritti all'attivazione.
        if ($user->tenant && ! $user->tenant->hasAccess()) {
            return redirect()->route('abbonamento')->with('success', 'Password salvata. Ora attiva SinistroPro scegliendo il piano.');
        }

        return redirect()->route('dashboard')->with('success', 'Password salvata.');
    }
}
