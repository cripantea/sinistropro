<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password provvisoria data dal superadmin: finché l'utente non ne sceglie una sua,
 * ogni pagina porta a "Scegli la tua password". Non vale quando il superadmin impersona.
 */
class EnsurePasswordCambiata
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password
            || $request->session()->has('impersonator_id')
            || $request->routeIs('password.scegli*', 'logout')) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(403, 'Scegli prima una nuova password.');
        }

        return redirect()->route('password.scegli');
    }
}
