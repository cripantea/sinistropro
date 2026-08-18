<?php

namespace App\Jobs;

use App\Models\WhatsappHistorySync;
use App\Models\WhatsappSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerWhatsappHistorySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    private const REJECTED_ERROR_CODE = '2593109';

    public function __construct(public readonly int $whatsappSessionId)
    {
        $this->onQueue('automations');
    }

    public function handle(): void
    {
        $session = WhatsappSession::find($this->whatsappSessionId);

        if (! $session || $session->status !== 'active') {
            Log::warning('TriggerWhatsappHistorySyncJob: sessione non trovata o non attiva', [
                'whatsapp_session_id' => $this->whatsappSessionId,
            ]);

            return;
        }

        $this->requestSync($session, 'smb_app_state_sync', null);
        $this->requestSync($session, 'history', 0);
    }

    private function requestSync(WhatsappSession $session, string $syncType, ?int $phase): void
    {
        $graphVersion = config('services.facebook.graph_version');
        $token = $session->access_token ?: config('services.whatsapp_cloud.token');

        $response = Http::withToken($token)
            ->timeout(15)
            ->post("https://graph.facebook.com/{$graphVersion}/{$session->phone_number_id}/smb_app_data", [
                'messaging_product' => 'whatsapp',
                'sync_type' => $syncType,
            ]);

        $sync = WhatsappHistorySync::forTenant($session->tenant_id)->firstWhere([
            'whatsapp_session_id' => $session->id,
            'sync_type' => $syncType,
            'phase' => $phase,
        ]);

        if (! $sync) {
            return;
        }

        $errorCode = (string) ($response->json('error.code') ?? '');

        if ($errorCode === self::REJECTED_ERROR_CODE) {
            // Il business ha rifiutato la condivisione di contatti/storico: il numero
            // resta comunque operativo per i messaggi live, solo lo storico non è disponibile.
            $sync->update([
                'status' => 'failed',
                'error_code' => $errorCode,
                'error_message' => $response->json('error.message'),
            ]);
            $session->update(['history_sync_status' => 'unavailable']);

            return;
        }

        if ($response->failed()) {
            Log::error('TriggerWhatsappHistorySyncJob: richiesta smb_app_data fallita', [
                'whatsapp_session_id' => $session->id,
                'sync_type' => $syncType,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $sync->update(['status' => 'failed', 'error_message' => $response->body()]);

            return;
        }

        $sync->update(['status' => 'in_progress']);
    }
}
