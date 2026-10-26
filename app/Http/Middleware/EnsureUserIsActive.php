<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlla a ogni richiesta (non solo al login) che l'account sia attivo: senza questo
 * una sessione aperta o il cookie "ricordami" restano validi dopo la disattivazione.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                abort(401, 'Account disabilitato.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Il tuo account è stato disabilitato. Contatta l\'amministratore.',
            ]);
        }

        return $next($request);
    }
}
