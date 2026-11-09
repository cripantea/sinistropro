<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Dall'indirizzo email ricava server IMAP/SMTP e istruzioni del provider, così chi
 * collega la casella scrive solo email e password.
 *
 * Ordine: provider noti per dominio (Gmail, Libero, ...) → record MX del dominio
 * (Google Workspace, Microsoft 365, Aruba su domini propri) → database pubblico di
 * Thunderbird (ISPDB) per dominio e per dominio dell'MX → niente (campi a mano).
 *
 * Microsoft (Outlook, Hotmail, Microsoft 365) non accetta più password per IMAP:
 * serve l'accesso con OAuth, quindi viene riconosciuto ma segnato non supportato.
 */
class EmailProviders
{
    private const PROVIDERS = [
        'gmail' => [
            'nome' => 'Gmail',
            'imap' => ['host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl'],
            'password_app' => true,
            'link' => 'https://myaccount.google.com/apppasswords',
            'istruzioni' => [
                'Serve la "password per le app" di Google, non la password normale.',
                'Se Google dice che non è disponibile, attiva prima la Verifica in due passaggi (myaccount.google.com/security).',
                'Apri il link qui sotto, scrivi "SinistroPro" come nome e premi Crea.',
                'Copia i 16 caratteri che compaiono e incollali nel campo Password.',
            ],
        ],
        'microsoft' => [
            'nome' => 'Outlook / Microsoft 365',
            'imap' => ['host' => 'outlook.office365.com', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls'],
            'supportato' => false,
            'nota' => 'Microsoft (Outlook, Hotmail, Live, Microsoft 365) dal 2024 non accetta più la password per collegare la posta ad altri programmi: serve l\'accesso con il pulsante Microsoft, che non è ancora disponibile. Scrivici e lo colleghiamo noi.',
        ],
        'yahoo' => [
            'nome' => 'Yahoo Mail',
            'imap' => ['host' => 'imap.mail.yahoo.com', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtp.mail.yahoo.com', 'port' => 465, 'encryption' => 'ssl'],
            'password_app' => true,
            'link' => 'https://login.yahoo.com/account/security',
            'istruzioni' => [
                'Serve una "password per app" di Yahoo, non la password normale.',
                'Apri il link qui sotto, scegli "Genera password per app", scrivi "SinistroPro" e premi Genera.',
                'Copia la password che compare e incollala nel campo Password.',
            ],
        ],
        'icloud' => [
            'nome' => 'iCloud Mail',
            'imap' => ['host' => 'imap.mail.me.com', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtp.mail.me.com', 'port' => 587, 'encryption' => 'tls'],
            'password_app' => true,
            'link' => 'https://account.apple.com',
            'istruzioni' => [
                'Serve una "password specifica per le app" di Apple, non la password dell\'ID Apple.',
                'Apri il link qui sotto, vai su Accesso e sicurezza → Password specifiche per le app → Genera.',
                'Copia la password che compare e incollala nel campo Password.',
            ],
        ],
        'libero' => [
            'nome' => 'Libero Mail',
            'imap' => ['host' => 'imapmail.libero.it', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtp.libero.it', 'port' => 465, 'encryption' => 'ssl'],
            'istruzioni' => ['Usa la stessa password con cui entri in Libero Mail.'],
        ],
        'virgilio' => [
            'nome' => 'Virgilio Mail',
            'imap' => ['host' => 'in.virgilio.it', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'out.virgilio.it', 'port' => 465, 'encryption' => 'ssl'],
            'istruzioni' => ['Usa la stessa password con cui entri in Virgilio Mail.'],
        ],
        'tiscali' => [
            'nome' => 'Tiscali Mail',
            'imap' => ['host' => 'imap.tiscali.it', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtp.tiscali.it', 'port' => 465, 'encryption' => 'ssl'],
            'istruzioni' => ['Usa la stessa password con cui entri in Tiscali Mail.'],
        ],
        'aruba' => [
            'nome' => 'Aruba',
            'imap' => ['host' => 'imaps.aruba.it', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtps.aruba.it', 'port' => 465, 'encryption' => 'ssl'],
            'istruzioni' => ['Usa la password della casella (quella di webmail.aruba.it).'],
        ],
        'hostinger' => [
            'nome' => 'Hostinger',
            'imap' => ['host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl'],
            'smtp' => ['host' => 'smtp.hostinger.com', 'port' => 465, 'encryption' => 'ssl'],
            'istruzioni' => ['Usa la password della casella (quella della webmail Hostinger).'],
        ],
    ];

    private const DOMINI = [
        'gmail.com' => 'gmail', 'googlemail.com' => 'gmail',
        'outlook.com' => 'microsoft', 'outlook.it' => 'microsoft', 'hotmail.com' => 'microsoft',
        'hotmail.it' => 'microsoft', 'live.com' => 'microsoft', 'live.it' => 'microsoft', 'msn.com' => 'microsoft',
        'yahoo.com' => 'yahoo', 'yahoo.it' => 'yahoo', 'ymail.com' => 'yahoo', 'rocketmail.com' => 'yahoo',
        'icloud.com' => 'icloud', 'me.com' => 'icloud', 'mac.com' => 'icloud',
        'libero.it' => 'libero', 'inwind.it' => 'libero', 'iol.it' => 'libero', 'blu.it' => 'libero', 'giallo.it' => 'libero',
        'virgilio.it' => 'virgilio',
        'tiscali.it' => 'tiscali',
    ];

    /** Fine del nome host dell'MX → provider (domini propri ospitati da un provider noto). */
    private const MX = [
        'google.com' => 'gmail', 'googlemail.com' => 'gmail',
        'outlook.com' => 'microsoft',
        'aruba.it' => 'aruba',
        'hostinger.com' => 'hostinger',
    ];

    /**
     * @return array{chiave: string, nome: string, imap: array, smtp: array, username: string, supportato: bool, password_app: bool, link: ?string, istruzioni: string[], nota: ?string}|null
     */
    public function detect(string $email): ?array
    {
        $email = Str::lower(trim($email));
        $domain = Str::after($email, '@');
        if ($domain === '' || $domain === $email) {
            return null;
        }

        if (isset(self::DOMINI[$domain])) {
            return $this->provider(self::DOMINI[$domain], $email);
        }

        $mx = $this->mxHosts($domain);
        foreach ($mx as $host) {
            foreach (self::MX as $suffix => $key) {
                if ($host === $suffix || Str::endsWith($host, '.'.$suffix)) {
                    $found = $this->provider($key, $email);
                    if ($key === 'gmail') {
                        $found['nome'] = 'Google Workspace';
                        $found['istruzioni'][] = 'Se la password per le app non compare, l\'amministratore di Google Workspace deve permetterla.';
                    }

                    return $found;
                }
            }
        }

        foreach (array_unique([$domain, ...array_map(fn ($h) => $this->baseDomain($h), $mx)]) as $lookup) {
            if ($found = $this->ispdb($lookup, $email)) {
                return $found;
            }
        }

        return null;
    }

    /** @return string[] host MX in minuscolo, dal più prioritario */
    public function mxHosts(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_MX) ?: [];
        usort($records, fn ($a, $b) => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));

        return array_values(array_filter(array_map(fn ($r) => Str::lower(rtrim($r['target'] ?? '', '.')), $records)));
    }

    private function provider(string $key, string $email): array
    {
        $p = self::PROVIDERS[$key];

        if ($key === 'microsoft' && MicrosoftMail::configured()) {
            $p['nota'] = 'Con Microsoft non serve la password: premi "Accedi con Microsoft" e scegli la casella. Se è una casella aziendale, la prima volta può servire l\'amministratore di Microsoft 365.';
        }

        return [
            'chiave' => $key,
            'nome' => $p['nome'],
            'imap' => $p['imap'],
            'smtp' => $p['smtp'],
            'username' => $email,
            'supportato' => $p['supportato'] ?? true,
            'password_app' => $p['password_app'] ?? false,
            'link' => $p['link'] ?? null,
            'istruzioni' => $p['istruzioni'] ?? [],
            'nota' => $p['nota'] ?? null,
        ];
    }

    /** Configurazione dal database pubblico di Thunderbird (autoconfig.thunderbird.net). */
    private function ispdb(string $domain, string $email): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://autoconfig.thunderbird.net/v1.1/'.rawurlencode($domain));
            if (! $response->successful()) {
                return null;
            }
            $xml = @simplexml_load_string($response->body());
        } catch (\Throwable) {
            return null;
        }

        if (! $xml || ! isset($xml->emailProvider)) {
            return null;
        }

        $provider = $xml->emailProvider;
        $imap = $this->ispdbServer($provider->xpath('incomingServer[@type="imap"]'), $email);
        $smtp = $this->ispdbServer($provider->xpath('outgoingServer[@type="smtp"]'), $email);
        if (! $imap || ! $smtp) {
            return null;
        }

        $nome = trim((string) ($provider->displayShortName ?: $provider->displayName)) ?: $domain;

        // Microsoft raggiunto tramite MX di un dominio non in elenco.
        if (Str::endsWith($imap['host'], 'office365.com') || Str::endsWith($imap['host'], 'outlook.com')) {
            return $this->provider('microsoft', $email);
        }

        return [
            'chiave' => 'ispdb',
            'nome' => $nome,
            'imap' => array_diff_key($imap, ['username' => 1]),
            'smtp' => array_diff_key($smtp, ['username' => 1]),
            'username' => $imap['username'],
            'supportato' => true,
            'password_app' => false,
            'link' => null,
            'istruzioni' => ['Usa la password della casella email.'],
            'nota' => null,
        ];
    }

    /** Primo server SSL/STARTTLS della lista (mai in chiaro). */
    private function ispdbServer(array $servers, string $email): ?array
    {
        foreach ($servers as $server) {
            $encryption = match (Str::upper((string) $server->socketType)) {
                'SSL' => 'ssl',
                'STARTTLS' => 'tls',
                default => null,
            };
            if (! $encryption || trim((string) $server->hostname) === '') {
                continue;
            }

            $username = str_replace(
                ['%EMAILADDRESS%', '%EMAILLOCALPART%', '%EMAILDOMAIN%'],
                [$email, Str::before($email, '@'), Str::after($email, '@')],
                (string) $server->username ?: '%EMAILADDRESS%',
            );

            return [
                'host' => Str::lower(trim((string) $server->hostname)),
                'port' => (int) $server->port,
                'encryption' => $encryption,
                'username' => $username,
            ];
        }

        return null;
    }

    private function baseDomain(string $host): string
    {
        $parts = explode('.', $host);

        return implode('.', array_slice($parts, -2));
    }
}
