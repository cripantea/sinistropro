<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Webklex\PHPIMAP\ClientManager;

/**
 * Prova login IMAP (lettura) e SMTP (invio) prima di salvare una casella, senza
 * mandare email. Gli errori tornano in italiano semplice, con il suggerimento giusto.
 */
class EmailConnectionTester
{
    /**
     * @param  array{username: string, password: string, imap_host: string, imap_port: int, imap_encryption: string, host: string, port: int, encryption: string}  $c
     * @return string|null  null se tutto ok, altrimenti il messaggio d'errore
     */
    public function test(array $c, ?string $provider = null): ?string
    {
        try {
            $this->imap($c);
        } catch (\Throwable $e) {
            return $this->spiega('lettura (IMAP)', $e, $provider);
        }

        try {
            $this->smtp($c);
        } catch (\Throwable $e) {
            return $this->spiega('invio (SMTP)', $e, $provider);
        }

        return null;
    }

    public function imap(array $c): void
    {
        $client = (new ClientManager())->make([
            'host' => $c['imap_host'],
            'port' => $c['imap_port'] ?: 993,
            'encryption' => $c['imap_encryption'] ?: 'ssl',
            'validate_cert' => true,
            'username' => $c['username'],
            'password' => $c['password'],
            'authentication' => ! empty($c['oauth']) ? 'oauth' : null,
            'timeout' => 20,
        ]);

        $client->connect();
        $client->disconnect();
    }

    public function smtp(array $c): void
    {
        $scheme = ($c['encryption'] ?? null) === 'ssl' || (int) $c['port'] === 465 ? 'smtps' : 'smtp';
        $transport = (new EsmtpTransportFactory())->create(
            new Dsn($scheme, $c['host'], $c['username'], $c['password'], (int) $c['port'] ?: null)
        );
        $transport->setTimeout(20);
        if (! empty($c['oauth'])) {
            $transport->setAuthenticators([new XOAuth2Authenticator()]);
        }

        $transport->start();
        $transport->stop();
    }

    public function spiega(string $fase, \Throwable $e, ?string $provider): string
    {
        $dettaglio = $this->messaggi($e);
        $testo = Str::lower($dettaglio);

        $passwordApp = in_array($provider, ['gmail', 'yahoo', 'icloud'], true);

        if (Str::contains($testo, ['smtpclientauthentication is disabled', '5.7.139'])) {
            return 'Microsoft blocca l\'invio da programmi esterni: chi gestisce Microsoft 365 deve attivare "SMTP autenticato" per questa casella (Interfaccia di amministrazione → Utenti → la casella → Posta → Gestisci app di posta).';
        }

        if ($provider === 'microsoft' && Str::contains($testo, ['authenticate failed', 'authentication failed', 'auth failed', 'authenticationfailed', '535'])) {
            return "Microsoft ha rifiutato l'accesso per la {$fase}: chi gestisce Microsoft 365 deve controllare che IMAP e SMTP autenticato siano attivi per questa casella (Utenti → la casella → Posta → Gestisci app di posta).";
        }

        if (Str::contains($testo, ['application-specific password', 'app password', 'apppassword'])) {
            return 'La casella chiede la "password per le app", non la password normale. Segui i passaggi qui sopra e incolla quella.';
        }

        if (Str::contains($testo, ['authenticationfailed', 'invalid credentials', 'authentication failed', '535', 'username and password not accepted', 'login failed', 'auth failed', 'authenticate failed', 'credenziali', 'incorrect authentication'])) {
            return $passwordApp
                ? 'Email o password non accettate. Con questo provider serve la "password per le app" (16 caratteri), non quella con cui entri di solito.'
                : 'Email o password non accettate. Controlla di averle scritte giuste (attenzione a maiuscole e spazi).';
        }

        if (Str::contains($testo, ['basicauthblocked', 'basic authentication'])) {
            return 'Il provider non accetta più la password per i programmi esterni (serve l\'accesso con OAuth). Scrivici e lo colleghiamo noi.';
        }

        if (Str::contains($testo, ['certificate', 'ssl', 'tls'])) {
            return "Connessione sicura non riuscita per la {$fase}: controlla server, porta e cifratura nelle impostazioni avanzate. ({$this->corto($dettaglio)})";
        }

        if (Str::contains($testo, ['timed out', 'timeout', 'getaddrinfo', 'could not connect', 'connection refused', 'name or service not known', 'unable to connect', 'connection could not be established', 'connection setup failed'])) {
            return "Il server per la {$fase} non risponde: controlla server e porta nelle impostazioni avanzate. ({$this->corto($dettaglio)})";
        }

        return "Collegamento non riuscito ({$fase}): {$this->corto($dettaglio)}";
    }

    /** Messaggi di tutta la catena: php-imap mette il motivo vero nell'eccezione precedente. */
    private function messaggi(\Throwable $e): string
    {
        $parti = [];
        for ($x = $e; $x; $x = $x->getPrevious()) {
            $parti[] = trim($x->getMessage());
        }

        return implode(' | ', array_unique(array_filter($parti)));
    }

    private function corto(string $s): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $s), 220);
    }
}
