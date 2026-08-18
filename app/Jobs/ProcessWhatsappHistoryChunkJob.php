<?php

namespace App\Jobs;

use App\Events\WhatsappEvent;
use App\Models\WhatsappConversation;
use App\Models\WhatsappHistorySync;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessWhatsappHistoryChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    /**
     * @param  array<string, mixed>  $chunk  payload grezzo del campo webhook "history"
     */
    public function __construct(
        public readonly int $whatsappSessionId,
        public readonly array $chunk,
    ) {
        $this->onQueue('automations');
    }

    public function handle(): void
    {
        $session = WhatsappSession::find($this->whatsappSessionId);

        if (! $session) {
            Log::warning('ProcessWhatsappHistoryChunkJob: sessione non trovata', [
                'whatsapp_session_id' => $this->whatsappSessionId,
            ]);

            return;
        }

        $phase = $this->chunk['history_context']['phase'] ?? $this->chunk['phase'] ?? 0;
        $chunkOrder = $this->chunk['history_context']['chunk_order'] ?? $this->chunk['chunk_order'] ?? null;
        $progress = $this->chunk['history_context']['progress'] ?? $this->chunk['progress'] ?? null;

        $messages = $this->chunk['messages'] ?? [];
        $imported = 0;

        foreach ($messages as $message) {
            if ($this->importMessage($session, $this->chunk['contacts'] ?? [], $message)) {
                $imported++;
            }
        }

        $sync = WhatsappHistorySync::forTenant($session->tenant_id)->firstWhere([
            'whatsapp_session_id' => $session->id,
            'sync_type' => 'history',
            'phase' => (int) $phase,
        ]);

        if ($sync) {
            $sync->update([
                'status' => $progress >= 100 ? 'completed' : 'in_progress',
                'progress' => $progress,
                'last_chunk_order' => $chunkOrder,
                'completed_at' => $progress >= 100 ? now() : null,
            ]);
        }

        // Un solo evento per chunk, non uno per messaggio: un chunk di history può
        // contenere centinaia di messaggi, un broadcast a messaggio inonderebbe la UI.
        broadcast(new WhatsappEvent($session->tenant_id, 'history_sync_progress', [
            'phase' => (int) $phase,
            'progress' => $progress,
            'imported' => $imported,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<string, mixed>  $message
     */
    private function importMessage(WhatsappSession $session, array $contacts, array $message): bool
    {
        $waMessageId = is_string($message['id'] ?? null) ? $message['id'] : null;

        if ($waMessageId && WhatsappMessage::where('tenant_id', $session->tenant_id)->where('wa_message_id', $waMessageId)->exists()) {
            return false;
        }

        $direction = ($message['from'] ?? null) === $session->display_phone_number ? 'outbound' : 'inbound';
        $counterpart = $direction === 'outbound' ? ($message['to'] ?? null) : ($message['from'] ?? null);
        $phoneNumber = preg_replace('/\D/', '', (string) $counterpart) ?? '';

        if ($phoneNumber === '') {
            return false;
        }

        $contact = collect($contacts)->firstWhere('wa_id', $counterpart);
        $timestamp = isset($message['timestamp'])
            ? Carbon::createFromTimestamp((int) $message['timestamp'])
            : now();

        $conversation = WhatsappConversation::forTenant($session->tenant_id)->firstOrCreate(
            ['tenant_id' => $session->tenant_id, 'phone_number' => $phoneNumber],
            ['contact_name' => $contact['profile']['name'] ?? null]
        );

        $body = $message['text']['body'] ?? null;
        $mediaType = ($message['type'] ?? 'text') !== 'text' ? $message['type'] : null;

        WhatsappMessage::create([
            'tenant_id' => $session->tenant_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => $direction,
            'source' => 'history',
            'body' => $body,
            'media_type' => $mediaType,
            'wa_message_id' => $waMessageId,
            'wa_timestamp' => $timestamp,
            'status' => $direction === 'outbound' ? 'sent' : 'received',
        ]);

        if (! $conversation->last_message_at || $timestamp->gt($conversation->last_message_at)) {
            $conversation->last_message_at = $timestamp;
            $conversation->last_message_preview = Str::limit((string) ($body ?? ($mediaType ? "[{$mediaType}]" : '')), 200);
            $conversation->save();
        }

        return true;
    }
}
