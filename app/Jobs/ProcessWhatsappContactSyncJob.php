<?php

namespace App\Jobs;

use App\Models\WhatsappConversation;
use App\Models\WhatsappSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsappContactSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $payload  payload grezzo del campo webhook "smb_app_state_sync"
     */
    public function __construct(
        public readonly int $whatsappSessionId,
        public readonly array $payload,
    ) {
        $this->onQueue('automations');
    }

    public function handle(): void
    {
        $session = WhatsappSession::find($this->whatsappSessionId);

        if (! $session) {
            Log::warning('ProcessWhatsappContactSyncJob: sessione non trovata', [
                'whatsapp_session_id' => $this->whatsappSessionId,
            ]);

            return;
        }

        $contacts = $this->payload['contacts'] ?? [];

        foreach ($contacts as $contact) {
            $action = $contact['action'] ?? 'add';
            $phoneNumber = preg_replace('/\D/', '', (string) ($contact['wa_id'] ?? $contact['phone_number'] ?? '')) ?? '';

            if ($phoneNumber === '') {
                continue;
            }

            if ($action === 'remove') {
                // Dati di business nostri, non della rubrica del telefono: la rimozione
                // dalla rubrica non deve cancellare conversazioni/messaggi già salvati.
                Log::info('ProcessWhatsappContactSyncJob: contatto rimosso dalla rubrica (nessuna eliminazione lato piattaforma)', [
                    'tenant_id' => $session->tenant_id,
                    'phone_number' => $phoneNumber,
                ]);

                continue;
            }

            WhatsappConversation::forTenant($session->tenant_id)->firstOrCreate(
                ['tenant_id' => $session->tenant_id, 'phone_number' => $phoneNumber],
                ['contact_name' => $contact['profile']['name'] ?? $contact['name'] ?? null]
            );
        }
    }
}
