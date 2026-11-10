<?php

namespace App\Services;

use App\Models\TenantMailSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Accesso OAuth alle caselle Microsoft (Outlook.com, Hotmail, Microsoft 365): Microsoft non
 * accetta più la password per IMAP/SMTP. Si chiede il permesso una volta, poi il refresh token
 * rinnova da solo il token di accesso (1 ora) per la lettura IMAP e l'invio SMTP (XOAUTH2).
 */
class MicrosoftMail
{
    public const SCOPES = 'offline_access openid email profile https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send';

    public const IMAP = ['host' => 'outlook.office365.com', 'port' => 993, 'encryption' => 'ssl'];

    public const SMTP = ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls'];

    public static function configured(): bool
    {
        return filled(config('services.microsoft_mail.client_id')) && filled(config('services.microsoft_mail.client_secret'));
    }

    public function authorizeUrl(string $state, ?string $loginHint = null): string
    {
        return $this->endpoint('authorize').'?'.http_build_query(array_filter([
            'client_id' => config('services.microsoft_mail.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => self::SCOPES,
            'state' => $state,
            'prompt' => 'select_account',
            'login_hint' => $loginHint,
        ]));
    }

    /**
     * Approvazione per tutta l'azienda fatta dall'amministratore di Microsoft 365, senza collegare
     * la sua casella: dopo, chiunque dell'azienda può collegare la propria (o una condivisa).
     */
    public function adminConsentUrl(string $state): string
    {
        return 'https://login.microsoftonline.com/organizations/v2.0/adminconsent?'.http_build_query([
            'client_id' => config('services.microsoft_mail.client_id'),
            'scope' => 'https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send',
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    /**
     * @return array{email: string, nome: ?string, refresh_token: string, access_token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function exchangeCode(string $code): array
    {
        $data = $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        $claims = $this->claims($data['id_token'] ?? '');
        $email = Str::lower((string) ($claims['email'] ?? $claims['preferred_username'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || empty($data['refresh_token'])) {
            throw new RuntimeException('Microsoft non ha restituito l\'indirizzo della casella o il permesso di accesso continuo.');
        }

        return [
            'email' => $email,
            'nome' => $claims['name'] ?? null,
            'refresh_token' => $data['refresh_token'],
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ];
    }

    /** Token valido per IMAP/SMTP: quello salvato se dura ancora 5 minuti, altrimenti rinnovato e salvato. */
    public function accessToken(TenantMailSettings $settings): string
    {
        if ($settings->oauth_access_token && $settings->oauth_expires_at?->isAfter(now()->addMinutes(5))) {
            return $settings->oauth_access_token;
        }

        if (! $settings->oauth_refresh_token) {
            throw new RuntimeException('Accesso Microsoft mancante: ricollega la casella da Casella email.');
        }

        try {
            $data = $this->token([
                'grant_type' => 'refresh_token',
                'refresh_token' => $settings->oauth_refresh_token,
            ]);
        } catch (RuntimeException $e) {
            // Permesso revocato (password cambiata, accesso tolto): SinistroPro mostra "Ricollega la casella".
            if ($e->getCode() === 401 && ! $settings->oauth_revocato_at) {
                $settings->forceFill(['oauth_revocato_at' => now()])->save();
            }
            throw $e;
        }

        $settings->forceFill([
            'oauth_access_token' => $data['access_token'],
            'oauth_refresh_token' => $data['refresh_token'] ?? $settings->oauth_refresh_token,
            'oauth_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
        ])->save();

        return $data['access_token'];
    }

    public function redirectUri(): string
    {
        return route('impostazioni.email.microsoft.callback');
    }

    private function token(array $params): array
    {
        $response = Http::asForm()->timeout(20)->post($this->endpoint('token'), $params + [
            'client_id' => config('services.microsoft_mail.client_id'),
            'client_secret' => config('services.microsoft_mail.client_secret'),
            'scope' => self::SCOPES,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            $errore = (string) ($response->json('error_description') ?: $response->json('error') ?: $response->status());
            throw new RuntimeException(
                $response->json('error') === 'invalid_grant'
                    ? 'Accesso Microsoft scaduto o revocato: ricollega la casella da Casella email.'
                    : 'Microsoft ha rifiutato l\'accesso: '.Str::limit(Str::before($errore, "\r"), 300),
                $response->json('error') === 'invalid_grant' ? 401 : 0
            );
        }

        return $response->json();
    }

    /** Dati dell'id_token: arriva direttamente da Microsoft via HTTPS, la firma non serve. */
    private function claims(string $jwt): array
    {
        $payload = explode('.', $jwt)[1] ?? '';
        $json = base64_decode(strtr($payload, '-_', '+/').str_repeat('=', (4 - strlen($payload) % 4) % 4));

        return json_decode($json ?: '[]', true) ?: [];
    }

    private function endpoint(string $tipo): string
    {
        return 'https://login.microsoftonline.com/'.config('services.microsoft_mail.tenant', 'common').'/oauth2/v2.0/'.$tipo;
    }
}
