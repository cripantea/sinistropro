<?php

namespace App\Jobs;

use App\Mail\AutomazioneNotificaMail;
use App\Models\Automation;
use App\Models\Cliente;
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

class ExecuteClienteAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly Cliente    $cliente,
        public readonly Automation $automation,
        public readonly string     $fieldName,
        public readonly ?array     $override = null,
    ) {
        $this->onQueue('automations');
    }

    public function handle(TenantMailerResolver $mailer, AutomationPlanner $planner): void
    {
        $cliente    = Cliente::with('tenant')->findOrFail($this->cliente->id);
        $automation = $this->automation;

        $fieldValue = $cliente->custom_fields[$this->fieldName] ?? null;

        // Con override (conferma dell'utente) si usano esattamente i destinatari scelti;
        // altrimenti il cliente dell'anagrafica.
        $recipients = $this->override['recipients'] ?? [['name' => $cliente->nome, 'email' => $cliente->email, 'phone' => $cliente->telefono]];

        foreach ($recipients as $r) {
            $compiled = $planner->compileCliente($automation->message_template, $cliente, $fieldValue);
            // {nome_cliente} = nome del destinatario scelto, se diverso dal cliente
            if (! empty($r['name']) && $r['name'] !== $cliente->nome) {
                $compiled = str_replace($cliente->nome, $r['name'], $compiled);
            }

            if (in_array($automation->channel, ['email', 'both'], true)) {
                $this->sendEmail($r['email'] ?? null, $r['name'] ?? null, $compiled, $cliente, $mailer, $this->override['cc'] ?? []);
            }

            if (in_array($automation->channel, ['whatsapp', 'both'], true)) {
                $this->sendWhatsapp($r['phone'] ?? null, $compiled, $cliente);
            }
        }

        Log::info('ExecuteClienteAutomationJob: eseguito', [
            'cliente_id'    => $cliente->id,
            'automation_id' => $automation->id,
            'field'         => $this->fieldName,
            'destinatari'   => count($recipients),
            'confermata'    => $this->override !== null,
        ]);
    }

    private function sendEmail(?string $email, ?string $name, string $compiled, Cliente $cliente, TenantMailerResolver $mailer, array $cc = []): void
    {
        if (! $email) {
            Log::warning('ExecuteClienteAutomationJob: email mancante', ['cliente_id' => $cliente->id]);
            $mailer->registraSaltata($cliente->tenant_id, "Cliente \"{$cliente->nome}\" senza email.", ['tipo' => 'promemoria_cliente', 'automation_id' => $this->automation->id]);
            return;
        }

        // Mittente/SMTP del tenant (come ExecuteAutomationJob): il mailer di default
        // non è configurato per i tenant e l'invio non partirebbe.
        $mailer->send(
            $cliente->tenant,
            $email,
            new AutomazioneNotificaMail(
                emailSubject:  "Promemoria — {$cliente->nome}",
                compiledBody:  $compiled,
                tenantName:    $cliente->tenant?->name ?? '',
                documentLinks: [],
            ),
            $cc,
            ['tipo' => 'promemoria_cliente', 'automation_id' => $this->automation->id]
        );
    }

    private function sendWhatsapp(?string $phone, string $compiled, Cliente $cliente): void
    {
        if (! $phone) {
            Log::warning('ExecuteClienteAutomationJob: telefono mancante', ['cliente_id' => $cliente->id]);
            return;
        }

        $session = WhatsappSession::where('tenant_id', $cliente->tenant_id)
            ->where('status', 'connected')
            ->first();

        if (! $session) {
            Log::warning('ExecuteClienteAutomationJob: nessuna sessione WA connessa', [
                'tenant_id' => $cliente->tenant_id,
            ]);
            return;
        }

        try {
            $to = preg_replace('/[^\d+]/', '', $phone);
            app(WhatsappCloudApiClient::class)->sendText($session->phone_number_id, $to, $compiled);
        } catch (\Throwable $e) {
            Log::error('ExecuteClienteAutomationJob: WA errore', [
                'cliente_id' => $cliente->id,
                'errore'     => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteClienteAutomationJob: job fallito', [
            'cliente_id'    => $this->cliente->id,
            'automation_id' => $this->automation->id,
            'errore'        => $exception->getMessage(),
        ]);
    }
}
