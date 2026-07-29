<?php

namespace App\Services;

use App\Exceptions\MailNotConfiguredException;
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
     * @param  string[]  $cc
     *
     * @throws MailNotConfiguredException se il tenant non ha
     *                                    una configurazione email attiva.
     */
    public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = []): void
    {
        $from = $this->fromFor($tenant);
        $mailable->from($from['address'], $from['name']);

        $pending = $this->mailerFor($tenant)->to($to);
        if (! empty($cc)) {
            $pending->cc($cc);
        }

        $pending->send($mailable);
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
