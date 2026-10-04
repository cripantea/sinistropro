<?php

namespace App\Services;

use App\Exceptions\MailNotConfiguredException;
use App\Models\EmailLog;
use App\Models\Tenant;
use App\Models\TenantMailSettings;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Risolve quale mailer/mittente usare per un tenant, in base alla sua
 * configurazione SMTP propria (tabella tenant_mail_settings).
 *
 * Nessun fallback silenzioso su un mailer condiviso: se il tenant non ha una
 * configurazione email attiva, l'invio fallisce esplicitamente con un
 * MailNotConfiguredException, così l'errore emerge subito (log chiaro) invece
 * di spedire email da un mittente sbagliato senza che nessuno se ne accorga.
 *
 * I job in coda (ExecuteAutomationJob, InviaEmailAvvisoPratica) girano su
 * worker separati dalla richiesta HTTP: per questo la risoluzione va sempre
 * fatta dentro il metodo handle() del job, subito prima dell'invio — mai
 * "prima" a livello di richiesta, perché la config al volo non sopravvive
 * al passaggio a un worker diverso.
 */
class TenantMailerResolver
{
    /**
     * Invia una Mailable usando il mittente/server del tenant.
     *
     * Ogni tentativo (riuscito o fallito) viene registrato in email_logs; l'eccezione
     * originale viene comunque rilanciata, così retry dei job e log restano invariati.
     *
     * @param  string[]  $cc
     * @param  array{tipo?: string, pratica_id?: ?int, automation_id?: ?int}  $logContext
     *
     * @throws MailNotConfiguredException se il tenant non ha
     *                                    una configurazione email attiva.
     */
    public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void
    {
        try {
            $from = $this->fromFor($tenant);
            $mailable->from($from['address'], $from['name']);

            $pending = $this->mailerFor($tenant)->to($to);
            if (! empty($cc)) {
                $pending->cc($cc);
            }

            $pending->send($mailable);
        } catch (\Throwable $e) {
            $this->registra($tenant, $to, $cc, $mailable, $logContext, 'failed', $e->getMessage());
            throw $e;
        }

        $this->registra($tenant, $to, $cc, $mailable, $logContext, 'sent');
    }

    /**
     * Registra nel log un'email che non è stata nemmeno tentata (es. nessun destinatario).
     */
    public function registraSaltata(Tenant|int $tenant, string $motivo, array $logContext = [], ?string $to = null, ?string $subject = null): void
    {
        EmailLog::registra([
            'tenant_id'     => $tenant instanceof Tenant ? $tenant->id : $tenant,
            'pratica_id'    => $logContext['pratica_id'] ?? null,
            'automation_id' => $logContext['automation_id'] ?? null,
            'tipo'          => $logContext['tipo'] ?? 'altro',
            'to_address'    => $to,
            'subject'       => $subject,
            'status'        => 'skipped',
            'error'         => $motivo,
        ]);
    }

    private function registra(Tenant $tenant, string $to, array $cc, Mailable $mailable, array $logContext, string $status, ?string $error = null): void
    {
        $subject = null;
        try {
            $subject = method_exists($mailable, 'envelope') ? $mailable->envelope()->subject : $mailable->subject;
        } catch (\Throwable) {
        }

        EmailLog::registra([
            'tenant_id'     => $tenant->id,
            'pratica_id'    => $logContext['pratica_id'] ?? null,
            'automation_id' => $logContext['automation_id'] ?? null,
            'tipo'          => $logContext['tipo'] ?? 'altro',
            'to_address'    => $to,
            'cc_addresses'  => $cc ?: null,
            'subject'       => $subject,
            'status'        => $status,
            'error'         => $error ? mb_substr($error, 0, 2000) : null,
        ]);
    }

    /**
     * @return array{address: string, name: ?string}
     *
     * @throws MailNotConfiguredException
     */
    public function fromFor(Tenant $tenant): array
    {
        $settings = $this->activeSettingsOrFail($tenant);

        return [
            'address' => $settings->from_address,
            'name' => $settings->from_name ?: $tenant->name,
        ];
    }

    private function mailerFor(Tenant $tenant): Mailer
    {
        $settings = $this->activeSettingsOrFail($tenant);

        return $this->buildMailer("tenant_{$tenant->id}", [
            'host' => $settings->host,
            'port' => $settings->port,
            'encryption' => $settings->encryption,
            'username' => $settings->username,
            'password' => $settings->password,
        ]);
    }

    /**
     * @throws MailNotConfiguredException
     */
    private function activeSettingsOrFail(Tenant $tenant): TenantMailSettings
    {
        $settings = $tenant->mailSettings;

        if (! $settings || ! $settings->is_active || ! $settings->host || ! $settings->from_address) {
            throw new MailNotConfiguredException(
                "Il tenant \"{$tenant->name}\" non ha una configurazione email attiva. Configurala in Superadmin > Tenant > Email."
            );
        }

        return $settings;
    }

    /**
     * Invia un'email di prova con una configurazione SMTP arbitraria (non
     * necessariamente salvata) — usata dal pulsante "Invia email di prova"
     * prima di affidarsi alla configurazione in produzione.
     *
     * @param  array{host: ?string, port: ?int, encryption: ?string, username: ?string, password: ?string, from_address: ?string, from_name: ?string}  $settings
     */
    public function sendTestWith(array $settings, string $to, Mailable $mailable): void
    {
        $mailable->from($settings['from_address'] ?? config('mail.from.address'), $settings['from_name'] ?? config('mail.from.name'));

        $this->buildMailer('tenant_test_'.uniqid(), $settings)
            ->to($to)
            ->send($mailable);
    }

    /**
     * @param  array{host: ?string, port: ?int, encryption: ?string, username: ?string, password: ?string}  $settings
     */
    private function buildMailer(string $name, array $settings): Mailer
    {
        config(["mail.mailers.{$name}" => [
            'transport' => 'smtp',
            'host' => $settings['host'],
            'port' => $settings['port'],
            'encryption' => $settings['encryption'] ?: null,
            'username' => $settings['username'],
            'password' => $settings['password'],
        ]]);

        return Mail::mailer($name);
    }
}
