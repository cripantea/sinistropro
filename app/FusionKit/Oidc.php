<?php

namespace App\FusionKit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Accesso con Fusion ID (OIDC, authorization code + PKCE). L'id_token arriva direttamente
 * dal token endpoint su TLS: per OIDC Core §3.1.3.7 non serve verificarne la firma.
 */
class Oidc
{
    public static function enabled(): bool
    {
        return (bool) (config('fusionkit.issuer') && config('fusionkit.client_id') && config('fusionkit.client_secret'));
    }

    public static function legacyLoginEnabled(): bool
    {
        return ! self::enabled() || config('fusionkit.legacy_login');
    }

    public static function discovery(): array
    {
        return Cache::remember('fusionkit.discovery', 3600, function () {
            $res = Http::timeout(10)->get(config('fusionkit.issuer').'/.well-known/openid-configuration');
            if (! $res->ok()) {
                throw new \RuntimeException('Fusion ID non raggiungibile ('.$res->status().')');
            }

            return $res->json();
        });
    }

    public static function safeReturnTo(?string $value): ?string
    {
        if (! $value || ! str_starts_with($value, '/') || str_starts_with($value, '//') || str_starts_with($value, '/\\')) {
            return null;
        }

        return $value;
    }

    public static function authorizationUrl(Request $request, ?string $returnTo, bool $google): string
    {
        $d = self::discovery();
        $state = KitToken::b64(random_bytes(24));
        $nonce = KitToken::b64(random_bytes(24));
        $verifier = KitToken::b64(random_bytes(48));
        $request->session()->put('fusion_oidc', [
            'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier,
            'returnTo' => self::safeReturnTo($returnTo), 'at' => time(),
        ]);

        $scopes = ['openid', 'email', 'profile', 'offline_access'];
        if ($google && config('fusionkit.google_idp_id')) {
            $scopes[] = 'urn:zitadel:iam:org:idp:id:'.config('fusionkit.google_idp_id');
        }

        return $d['authorization_endpoint'].'?'.http_build_query([
            'client_id' => config('fusionkit.client_id'),
            'redirect_uri' => config('fusionkit.app_url').'/auth/fusion/callback',
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => KitToken::b64(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'ui_locales' => 'it',
        ]);
    }

    /** @return array{claims: array, returnTo: ?string} */
    public static function finish(Request $request): array
    {
        if ($error = $request->query('error')) {
            throw new LinkError((string) ($request->query('error_description') ?: $error));
        }
        $st = $request->session()->pull('fusion_oidc');
        if (! is_array($st) || time() - ($st['at'] ?? 0) > 600) {
            throw new LinkError('Sessione di accesso scaduta: riprova.');
        }
        if (! hash_equals($st['state'], (string) $request->query('state'))) {
            throw new LinkError('Richiesta di accesso non valida: riprova.');
        }
        $code = (string) $request->query('code');
        if ($code === '') {
            throw new LinkError('Codice di accesso mancante.');
        }

        $d = self::discovery();
        $res = Http::asForm()->timeout(15)
            ->withBasicAuth(rawurlencode((string) config('fusionkit.client_id')), rawurlencode((string) config('fusionkit.client_secret')))
            ->post($d['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('fusionkit.app_url').'/auth/fusion/callback',
                'code_verifier' => $st['verifier'],
            ]);
        if (! $res->ok() || ! $res->json('id_token')) {
            throw new LinkError('Fusion ID ha rifiutato l\'accesso ('.$res->status().').');
        }
        $parts = explode('.', $res->json('id_token'));
        $c = json_decode(KitToken::unb64($parts[1] ?? ''), true) ?: [];
        $aud = (array) ($c['aud'] ?? []);
        if (($c['iss'] ?? null) !== $d['issuer'] || ! in_array(config('fusionkit.client_id'), $aud, true)
            || ($c['exp'] ?? 0) < time() - 30 || ($c['nonce'] ?? null) !== $st['nonce']) {
            throw new LinkError('Token di Fusion ID non valido.');
        }
        if (empty($c['sub']) || empty($c['email'])) {
            throw new LinkError('Fusion ID non ha fornito l\'email.');
        }

        return [
            'claims' => [
                'sub' => $c['sub'],
                'email' => strtolower(trim($c['email'])),
                'email_verified' => ($c['email_verified'] ?? false) === true,
                'name' => $c['name'] ?? $c['email'],
                'given_name' => $c['given_name'] ?? null,
                'family_name' => $c['family_name'] ?? null,
                'amr' => (array) ($c['amr'] ?? []),
            ],
            'returnTo' => $st['returnTo'] ?? null,
        ];
    }

    public static function logoutUrl(string $postLogoutPath = '/login'): string
    {
        $d = self::discovery();

        return $d['end_session_endpoint'].'?'.http_build_query([
            'client_id' => config('fusionkit.client_id'),
            'post_logout_redirect_uri' => config('fusionkit.app_url').$postLogoutPath,
        ]);
    }
}
