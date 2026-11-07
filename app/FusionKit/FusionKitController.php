<?php

namespace App\FusionKit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Fusion Kit per Laravel: login con Fusion ID e API per Fusion Control.
 * Sorgente unica in fusion-id/kit/laravel (copiata da kit/sync.sh: non modificare qui).
 */
class FusionKitController extends Controller
{
    public function __construct(private Adapter $adapter)
    {
    }

    // ── Login ────────────────────────────────────────────────────────────────

    public function login(Request $request): RedirectResponse
    {
        if (! Oidc::enabled()) {
            return redirect('/login');
        }

        return redirect()->away(Oidc::authorizationUrl($request, $request->query('returnTo'), $request->query('google') === '1'));
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            ['claims' => $claims, 'returnTo' => $returnTo] = Oidc::finish($request);
            $user = $this->adapter->linkUser($claims);
        } catch (LinkError $e) {
            return redirect('/login')->with('fusion_error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect('/login')->with('fusion_error', 'Accesso non riuscito: riprova.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('fusion_sso', true);

        return redirect($returnTo ?? $this->adapter->homeFor($user));
    }

    /** "Entra come" da Fusion Control: link firmato valido 60 secondi. */
    public function impersonate(Request $request): RedirectResponse
    {
        $p = KitToken::verify((string) $request->query('token'));
        if (! $p || ($p['purpose'] ?? null) !== 'impersonate' || empty($p['userId'])) {
            return redirect('/login')->with('fusion_error', 'Link scaduto.');
        }
        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveById($p['userId']);
        if (! $user) {
            return redirect('/login')->with('fusion_error', 'Utente non trovato.');
        }
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect($this->adapter->homeFor($user));
    }

    /**
     * Da chiamare nel logout del prodotto PRIMA di invalidare la sessione:
     * restituisce dove mandare il browser.
     */
    public static function logoutTarget(Request $request): string
    {
        $viaFusion = (bool) $request->session()->get('fusion_sso', false);
        if ($viaFusion && Oidc::enabled()) {
            try {
                return Oidc::logoutUrl('/login');
            } catch (\Throwable) {
                return '/login';
            }
        }

        return '/';
    }

    // ── API per Fusion Control ───────────────────────────────────────────────

    private function authorized(Request $request): bool
    {
        $secret = (string) config('fusionkit.kit_secret');

        return $secret !== '' && hash_equals($secret, (string) $request->bearerToken());
    }

    public function health(Request $request): JsonResponse
    {
        $started = microtime(true);
        $checks = [];
        foreach ($this->adapter->checks() as $name => $fn) {
            $t = microtime(true);
            try {
                $checks[$name] = ['ok' => (bool) $fn(), 'ms' => (int) round((microtime(true) - $t) * 1000)];
            } catch (\Throwable $e) {
                $checks[$name] = ['ok' => false, 'ms' => (int) round((microtime(true) - $t) * 1000), 'error' => mb_substr($e->getMessage(), 0, 200)];
            }
        }
        $ok = collect($checks)->every(fn ($c) => $c['ok']);
        if (! $this->authorized($request)) {
            return response()->json(['ok' => $ok], $ok ? 200 : 503);
        }

        return response()->json([
            'ok' => $ok,
            'product' => config('fusionkit.product'),
            'release' => env('FUSION_RELEASE') ?: env('GIT_COMMIT'),
            'checks' => $checks,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'time' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }

    public function tenants(Request $request): JsonResponse
    {
        abort_unless($this->authorized($request), 401);

        return response()->json(['tenants' => $this->adapter->tenants()]);
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless($this->authorized($request), 401);

        return response()->json(['users' => $this->adapter->users()]);
    }

    public function suspend(Request $request, string $id): JsonResponse
    {
        abort_unless($this->authorized($request), 401);
        $this->adapter->setTenantSuspended($id, true);

        return response()->json(['ok' => true]);
    }

    public function resume(Request $request, string $id): JsonResponse
    {
        abort_unless($this->authorized($request), 401);
        $this->adapter->setTenantSuspended($id, false);

        return response()->json(['ok' => true]);
    }

    public function impersonateLink(Request $request): JsonResponse
    {
        abort_unless($this->authorized($request), 401);
        $tenantId = (string) $request->input('tenantId');
        $userId = $this->adapter->impersonationTarget($tenantId, $request->input('userId'));
        if (! $userId) {
            return response()->json(['error' => 'Nessun utente attivo per questo cliente'], 404);
        }
        $token = KitToken::sign([
            'purpose' => 'impersonate',
            'userId' => (string) $userId,
            'actor' => (string) $request->input('actor', ''),
            'reason' => (string) $request->input('reason', ''),
        ], 60);

        return response()->json(['url' => config('fusionkit.app_url').'/auth/fusion/impersonate?token='.urlencode($token)]);
    }

    public function revokeSessions(Request $request): JsonResponse
    {
        abort_unless($this->authorized($request), 401);

        return response()->json(['revoked' => $this->adapter->revokeSessions(strtolower((string) $request->input('email')))]);
    }
}
