<?php

namespace App\Services;

use App\Models\TenantMailSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Accesso OAuth alle caselle Gmail / Google Workspace (alternativa alla "password per le app").
 * Scope https://mail.google.com/ (IMAP e SMTP via XOAUTH2). App Google non verificata: avviso
 * "app non verificata" al login e massimo 100 account; deve stare "In produzione", altrimenti
 * Google fa scadere il collegamento dopo 7 giorni.
 */
class GoogleMail
{
    public const SCOPES = 'openid email profile https://mail.google.com/';

    public const IMAP = ['host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl'];

    public const SMTP = ['host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl'];

    public static function configured(): bool
    {
        return filled(config('services.google_mail.client_id')) && filled(config('services.google_mail.client_secret'));
    }

    public function authorizeUrl(string $state, ?string $loginHint = null): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(array_filter([
            'client_id' => config('services.google_mail.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPES,
            'state' => $state,
            // offline + consent: Google dà il refresh token (senza, il collegamento durerebbe un'ora).
            'access_type' => 'offline',
            'prompt' => 'consent select_account',
            'include_granted_scopes' => 'true',
            'login_hint' => $loginHint,
        ]));
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

        if (! Str::contains((string) ($data['scope'] ?? ''), 'https://mail.google.com/')) {
            throw new RuntimeException('Nella schermata di Google va lasciata la spunta su "Leggere, scrivere, inviare ed eliminare tutte le email": senza, SinistroPro non vede la posta. Riprova.');
        }

        $claims = $this->claims($data['id_token'] ?? '');
        $email = Str::lower((string) ($claims['email'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || empty($data['refresh_token'])) {
            throw new RuntimeException('Google non ha restituito l\'indirizzo della casella o il permesso di accesso continuo. Riprova.');
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
            throw new RuntimeException('Accesso Google mancante: ricollega la casella da Casella email.');
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
        return route('impostazioni.email.google.callback');
    }

    private function token(array $params): array
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', $params + [
            'client_id' => config('services.google_mail.client_id'),
            'client_secret' => config('services.google_mail.client_secret'),
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException(
                $response->json('error') === 'invalid_grant'
                    ? 'Accesso Google scaduto o revocato (succede se cambi la password di Gmail): ricollega la casella da Casella email.'
                    : 'Google ha rifiutato l\'accesso: '.Str::limit((string) ($response->json('error_description') ?: $response->json('error') ?: $response->status()), 300),
                $response->json('error') === 'invalid_grant' ? 401 : 0
            );
        }

        return $response->json();
    }

    /** Dati dell'id_token: arriva direttamente da Google via HTTPS, la firma non serve. */
    private function claims(string $jwt): array
    {
        $payload = explode('.', $jwt)[1] ?? '';
        $json = base64_decode(strtr($payload, '-_', '+/').str_repeat('=', (4 - strlen($payload) % 4) % 4));

        return json_decode($json ?: '[]', true) ?: [];
    }
}
