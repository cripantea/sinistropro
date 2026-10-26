<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sola lettura per i tenant senza abbonamento attivo: le pagine si aprono, ma niente
 * creazioni, modifiche o cancellazioni. Restano permessi abbonamento, profilo e logout.
 * Il superadmin non è mai bloccato (anche quando lavora nel contesto di un tenant).
 */
class EnsureTenantHasAccess
{
    private const ALWAYS_ALLOWED = [
        'abbonamento*',
        'paga*',
        'logout',
        'profile.*',
        'password.*',
        'verification.*',
        'impersonate.leave',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role === 'superadmin' || $request->isMethodSafe() || $request->routeIs(...self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        // Query a parte, non $user->tenant: la relazione caricata finirebbe nelle props Inertia di auth.user.
        $tenant = $user->tenant()->first();
        if (! $tenant || $tenant->hasAccess()) {
            return $next($request);
        }

        $message = 'Abbonamento non attivo: SinistroPro è in sola lettura. Attivalo dalla pagina Abbonamento.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 402);
        }

        return back()->with('error', $message);
    }
}
