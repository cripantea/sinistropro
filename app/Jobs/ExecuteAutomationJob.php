<?php

namespace App\Jobs;

use App\Mail\AutomazioneNotificaMail;
use App\Models\Automation;
use App\Models\Pratica;
use App\Models\WhatsappSession;
use App\Services\AutomationPlanner;
use App\Services\TenantMailerResolver;
use App\Services\WhatsappCloudApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 30;

    /**
     * @param  array{recipients: array, cc: array}|null  $override  Destinatari/CC decisi dall'utente
     *         nella finestra di conferma. Se presente sostituisce quelli calcolati dall'automazione.
     */
    public function __construct(
        public readonly Pratica    $pratica,
        public readonly Automation $automation,
        public readonly ?array     $override = null,
    ) {
        $this->onQueue('automations');
    }

    public function handle(TenantMailerResolver $mailer, AutomationPlanner $planner): void
    {
        $pratica = Pratica::with([
            'tenant',
            'utenteCreatore',
            'currentStatus',
            'allegati',
            'cliente',
            'ispezioni' => fn ($q) => $q->latest()->limit(1)->with(['peritoContatto', 'carrozzeriaContatto', 'assegnatoa', 'carrozzeria']),
        ])->findOrFail($this->pratica->id);

        $automation = $this->automation->loadMissing('documentCategories');

        if ($this->override !== null) {
            $recipientsTo = $this->override['recipients'] ?? [];
            $ccEmails     = $this->override['cc'] ?? [];
        } else {
            $recipientsTo = $planner->defaultRecipientsPratica($pratica, $automation);
            $ccEmails     = array_column($planner->defaultCc($automation), 'email');
        }

        // Teniamo solo chi ha un recapito utilizzabile sul canale scelto.
        $recipientsTo = array_values(array_filter($recipientsTo, fn ($r) => $this->hasContactFor($automation->channel, $r)));

        if (empty($recipientsTo)) {
            Log::warning('ExecuteAutomationJob: nessun destinatario utilizzabile, skip', [
                'pratica_id'    => $pratica->id,
                'automation_id' => $automation->id,
            ]);
            $mailer->registraSaltata(
                $pratica->tenant_id,
                'Nessun destinatario con recapito valido (cliente senza email/telefono, o carrozzeria/perito non assegnato).',
                $this->logContext($pratica)
            );

            return;
        }

        $documentLinks = $planner->documentLinks($pratica, $automation);

        foreach ($recipientsTo as $recipient) {
            $compiledMessage = $planner->compilePratica($automation->message_template, $pratica, $recipient['name'] ?? null);
            $this->sendViaChannel($automation->channel, $recipient, $compiledMessage, $pratica, $documentLinks, $ccEmails, $mailer);
        }

        Log::info('ExecuteAutomationJob: eseguito con successo', [
            'pratica_id'    => $pratica->id,
            'automation_id' => $automation->id,
            'to_count'      => count($recipientsTo),
            'cc_count'      => count($ccEmails),
            'confermata'    => $this->override !== null,
        ]);
    }

    private function hasContactFor(string $channel, array $recipient): bool
    {
        $email = ! empty($recipient['email']);
        $phone = ! empty($recipient['phone']);

        return match ($channel) {
            'whatsapp' => $phone,
            'both'     => $email || $phone,
            default    => $email,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Invio sul canale
    // ─────────────────────────────────────────────────────────────────────────

    private function sendViaChannel(
        string $channel,
        array $recipient,
        string $compiledMessage,
        Pratica $pratica,
        array $documentLinks,
        array $ccEmails,
        TenantMailerResolver $mailer
    ): void {
        if (in_array($channel, ['email', 'both'], true)) {
            $this->sendEmail($recipient, $compiledMessage, $pratica, $documentLinks, $ccEmails, $mailer);
        }

        if (in_array($channel, ['whatsapp', 'both'], true)) {
            $this->sendWhatsapp($recipient, $compiledMessage, $pratica);
        }
    }

    private function sendEmail(array $recipient, string $compiledMessage, Pratica $pratica, array $documentLinks, array $ccEmails, TenantMailerResolver $mailer): void
    {
        if (! $recipient['email']) {
            Log::warning('ExecuteAutomationJob: email vuota, invio saltato', [
                'pratica_id'    => $pratica->id,
                'automation_id' => $this->automation->id,
            ]);
            $mailer->registraSaltata($pratica->tenant_id, 'Email del destinatario mancante.', $this->logContext($pratica));
            return;
        }

        $subject = "Sinistro #{$pratica->id} — {$pratica->currentStatus?->name}";

        $mailer->send(
            $pratica->tenant,
            $recipient['email'],
            new AutomazioneNotificaMail(
                emailSubject:  $subject,
                compiledBody:  $compiledMessage,
                tenantName:    $pratica->tenant?->name ?? '',
                documentLinks: $documentLinks,
            ),
            $ccEmails,
            $this->logContext($pratica)
        );
    }

    /** @return array{tipo: string, pratica_id: int, automation_id: int} */
    private function logContext(Pratica $pratica): array
    {
        return ['tipo' => 'automazione', 'pratica_id' => $pratica->id, 'automation_id' => $this->automation->id];
    }

    private function sendWhatsapp(array $recipient, string $compiledMessage, Pratica $pratica): void
    {
        $phone = $recipient['phone'] ?? null;

        if (! $phone) {
            Log::warning('ExecuteAutomationJob: WhatsApp skip — numero telefono mancante', [
                'pratica_id'    => $pratica->id,
                'automation_id' => $this->automation->id,
            ]);
            return;
        }

        $session = WhatsappSession::where('tenant_id', $pratica->tenant_id)
            ->where('status', 'connected')
            ->first();

        if (! $session) {
            Log::warning('ExecuteAutomationJob: WhatsApp skip — nessuna sessione connessa', [
                'pratica_id'    => $pratica->id,
                'tenant_id'     => $pratica->tenant_id,
            ]);
            return;
        }

        try {
            $to = preg_replace('/[^\d+]/', '', $phone);
            app(WhatsappCloudApiClient::class)->sendText($session->phone_number_id, $to, $compiledMessage);

            Log::info('ExecuteAutomationJob: WhatsApp inviato', [
                'pratica_id'    => $pratica->id,
                'automation_id' => $this->automation->id,
                'to'            => $to,
            ]);
        } catch (\Throwable $e) {
            Log::error('ExecuteAutomationJob: WhatsApp errore', [
                'pratica_id'    => $pratica->id,
                'automation_id' => $this->automation->id,
                'errore'        => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteAutomationJob: job fallito definitivamente', [
            'pratica_id'    => $this->pratica->id,
            'automation_id' => $this->automation->id,
            'errore'        => $exception->getMessage(),
        ]);
    }
}
