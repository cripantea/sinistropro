<?php

namespace App\Http\Controllers;

use App\Events\WhatsappEvent;
use App\Jobs\ProcessWhatsappContactSyncJob;
use App\Jobs\ProcessWhatsappHistoryChunkJob;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        return $request->isMethod('get')
            ? $this->verify($request)
            : $this->receive($request);
    }

    private function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge', '');

        if ($mode === 'subscribe' && $token === config('services.whatsapp_cloud.verify_token')) {
            return response($challenge, 200);
        }

        return response('', 403);
    }

    private function receive(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('WhatsappWebhookController: firma webhook non valida');

            return response('', 403);
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];

                match ($field) {
                    'messages' => $this->handleMessagesField($value),
                    'account_update' => $this->handleAccountUpdate($value),
                    'history' => $this->dispatchHistoryChunk($value),
                    'smb_app_state_sync' => $this->dispatchContactSync($value),
                    'smb_message_echoes' => $this->handleMessageEchoesField($value),
                    default => Log::info('WhatsappWebhookController: campo webhook non gestito', ['field' => $field]),
                };
            }
        }

        return response()->json(['ok' => true]);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.whatsapp_cloud.app_secret');
        $header = $request->header('X-Hub-Signature-256', '');

        if (! $secret || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, substr($header, strlen('sha256=')));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Campo "messages": messaggi/stati in tempo reale (invariato rispetto a prima
    // dell'introduzione della coexistence, solo isolato in un metodo dedicato)
    // ─────────────────────────────────────────────────────────────────────────

    private function handleMessagesField(array $value): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (! is_string($phoneNumberId)) {
            return;
        }

        $session = WhatsappSession::where('phone_number_id', $phoneNumberId)
            ->where('status', 'active')
            ->first();

        if (! $session) {
            Log::warning('WhatsappWebhookController: nessun tenant collegato a questo numero', ['phoneNumberId' => $phoneNumberId]);

            return;
        }

        foreach ($value['messages'] ?? [] as $message) {
            $this->handleMessage($session, $value['contacts'] ?? [], $message);
        }

        foreach ($value['statuses'] ?? [] as $status) {
            $this->handleStatus($session, $status);
        }
    }

    private function handleMessage(WhatsappSession $session, array $contacts, array $message): void
    {
        $phoneNumber = $this->normalizePhone((string) ($message['from'] ?? ''));
        if ($phoneNumber === '') {
            Log::warning('WhatsappWebhookController: impossibile determinare il numero mittente', ['message' => $message]);

            return;
        }

        $waMessageId = is_string($message['id'] ?? null) ? $message['id'] : null;

        // Meta può recapitare lo stesso evento più di una volta (retry di rete):
        // senza questo controllo, ogni retry duplicherebbe il messaggio in chat.
        if ($waMessageId && WhatsappMessage::where('tenant_id', $session->tenant_id)->where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $contact = collect($contacts)->firstWhere('wa_id', $message['from'] ?? null);

        $conversation = WhatsappConversation::forTenant($session->tenant_id)->firstOrCreate(
            ['tenant_id' => $session->tenant_id, 'phone_number' => $phoneNumber],
            ['contact_name' => $contact['profile']['name'] ?? null]
        );

        $body = $message['text']['body'] ?? null;
        $mediaType = $message['type'] !== 'text' ? $message['type'] : null;
        $timestamp = isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp']) : now();

        $whatsappMessage = WhatsappMessage::create([
            'tenant_id' => $session->tenant_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'source' => 'api',
            'body' => $body,
            'media_type' => $mediaType,
            'wa_message_id' => $waMessageId,
            'wa_timestamp' => $timestamp,
            'status' => 'received',
        ]);

        $conversation->last_message_at = $timestamp;
        $conversation->last_message_preview = Str::limit((string) ($body ?? ($mediaType ? '[media]' : '')), 200);
        $conversation->increment('unread_count');
        $conversation->save();

        $this->broadcast($session->tenant_id, 'message', [
            'conversation' => [
                'id' => $conversation->id,
                'phoneNumber' => $conversation->phone_number,
                'contactName' => $conversation->contact_name,
                'lastMessagePreview' => $conversation->last_message_preview,
                'lastMessageAt' => $conversation->last_message_at?->toIso8601String(),
                'unreadCount' => $conversation->unread_count,
            ],
            'message' => [
                'id' => $whatsappMessage->id,
                'direction' => 'inbound',
                'source' => 'api',
                'body' => $whatsappMessage->body,
                'mediaType' => $whatsappMessage->media_type,
                'status' => $whatsappMessage->status,
                'createdAt' => $whatsappMessage->created_at?->toIso8601String(),
            ],
        ]);
    }

    private function handleStatus(WhatsappSession $session, array $status): void
    {
        $waMessageId = $status['id'] ?? null;
        $newStatus = $status['status'] ?? null;

        if (! is_string($waMessageId) || ! is_string($newStatus)) {
            return;
        }

        $message = WhatsappMessage::where('tenant_id', $session->tenant_id)
            ->where('wa_message_id', $waMessageId)
            ->first();

        if (! $message) {
            return;
        }

        $message->update(['status' => $newStatus]);

        $this->broadcast($session->tenant_id, 'ack', [
            'messageId' => $message->id,
            'status' => $newStatus,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Campo "account_update": disconnessioni/riconnessioni della coexistence
    // ─────────────────────────────────────────────────────────────────────────

    private function handleAccountUpdate(array $value): void
    {
        $wabaId = $value['waba_info']['waba_id'] ?? null;
        $event = $value['event'] ?? null;

        if (! is_string($wabaId) || ! is_string($event)) {
            Log::info('WhatsappWebhookController: account_update senza waba_id/event riconoscibili', ['value' => $value]);

            return;
        }

        $session = WhatsappSession::where('waba_id', $wabaId)->first();

        if (! $session) {
            Log::warning('WhatsappWebhookController: account_update per una WABA non collegata', ['wabaId' => $wabaId, 'event' => $event]);

            return;
        }

        match ($event) {
            'PARTNER_REMOVED' => $session->update([
                'status' => 'disabled',
                'disconnection_reason' => $value['disconnection_info']['reason'] ?? 'PARTNER_REMOVED',
                'disconnected_at' => now(),
            ]),
            'account_offboarded' => $session->update([
                'status' => 'disabled',
                'disconnection_reason' => 'account_offboarded',
                'disconnected_at' => now(),
            ]),
            'account_reconnected' => $session->update([
                'status' => 'active',
                'disconnection_reason' => null,
                'disconnected_at' => null,
            ]),
            default => Log::info('WhatsappWebhookController: evento account_update non gestito', ['event' => $event]),
        };

        $this->broadcast($session->tenant_id, 'account_status', [
            'status' => $session->status,
            'event' => $event,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Campi "history" / "smb_app_state_sync": backfill asincrono via job dedicati.
    // Non processati inline: la history può portare fino a 180 giorni di messaggi,
    // rischiando timeout della risposta al webhook e retry a cascata da parte di Meta.
    // ─────────────────────────────────────────────────────────────────────────

    private function dispatchHistoryChunk(array $value): void
    {
        $this->logRawIfEnabled('history', $value);

        $session = $this->findSessionForSync($value);
        if (! $session) {
            return;
        }

        ProcessWhatsappHistoryChunkJob::dispatch($session->id, $value);
    }

    private function dispatchContactSync(array $value): void
    {
        $this->logRawIfEnabled('smb_app_state_sync', $value);

        $session = $this->findSessionForSync($value);
        if (! $session) {
            return;
        }

        ProcessWhatsappContactSyncJob::dispatch($session->id, $value);
    }

    private function findSessionForSync(array $value): ?WhatsappSession
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? $value['phone_number_id'] ?? null;

        if (! is_string($phoneNumberId)) {
            Log::warning('WhatsappWebhookController: impossibile determinare phone_number_id per sync history/contatti', ['value' => $value]);

            return null;
        }

        $session = WhatsappSession::where('phone_number_id', $phoneNumberId)->first();

        if (! $session) {
            Log::warning('WhatsappWebhookController: nessun tenant collegato a questo numero (sync)', ['phoneNumberId' => $phoneNumberId]);
        }

        return $session;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Campo "smb_message_echoes": messaggi mandati manualmente dall'app
    // WhatsApp Business del telefono, notificati per restare in sync con l'API.
    // ─────────────────────────────────────────────────────────────────────────

    private function handleMessageEchoesField(array $value): void
    {
        $this->logRawIfEnabled('smb_message_echoes', $value);

        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        if (! is_string($phoneNumberId)) {
            return;
        }

        $session = WhatsappSession::where('phone_number_id', $phoneNumberId)
            ->where('status', 'active')
            ->first();

        if (! $session) {
            Log::warning('WhatsappWebhookController: nessun tenant collegato a questo numero (echo)', ['phoneNumberId' => $phoneNumberId]);

            return;
        }

        $echoes = $value['message_echoes'] ?? $value['messages'] ?? [];

        foreach ($echoes as $echo) {
            $this->handleMessageEcho($session, $value['contacts'] ?? [], $echo);
        }
    }

    private function handleMessageEcho(WhatsappSession $session, array $contacts, array $message): void
    {
        // Le echo sono outbound (il telefono ha scritto al cliente): il destinatario
        // è "to", non "from" come per i messaggi in ingresso gestiti da handleMessage().
        $phoneNumber = $this->normalizePhone((string) ($message['to'] ?? ''));
        if ($phoneNumber === '') {
            Log::warning('WhatsappWebhookController: impossibile determinare il destinatario dell\'echo', ['message' => $message]);

            return;
        }

        $waMessageId = is_string($message['id'] ?? null) ? $message['id'] : null;

        if ($waMessageId && WhatsappMessage::where('tenant_id', $session->tenant_id)->where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $contact = collect($contacts)->firstWhere('wa_id', $message['to'] ?? null);

        $conversation = WhatsappConversation::forTenant($session->tenant_id)->firstOrCreate(
            ['tenant_id' => $session->tenant_id, 'phone_number' => $phoneNumber],
            ['contact_name' => $contact['profile']['name'] ?? null]
        );

        $body = $message['text']['body'] ?? null;
        $mediaType = ($message['type'] ?? 'text') !== 'text' ? $message['type'] : null;
        $timestamp = isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp']) : now();

        $whatsappMessage = WhatsappMessage::create([
            'tenant_id' => $session->tenant_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'source' => 'echo',
            'body' => $body,
            'media_type' => $mediaType,
            'wa_message_id' => $waMessageId,
            'wa_timestamp' => $timestamp,
            'status' => 'sent',
        ]);

        $conversation->last_message_at = $timestamp;
        $conversation->last_message_preview = Str::limit((string) ($body ?? ($mediaType ? '[media]' : '')), 200);
        $conversation->save();

        // A differenza della history, gli echo vanno in realtime: un operatore che
        // risponde a mano dal telefono deve essere visibile subito agli altri colleghi.
        $this->broadcast($session->tenant_id, 'message', [
            'conversation' => [
                'id' => $conversation->id,
                'phoneNumber' => $conversation->phone_number,
                'contactName' => $conversation->contact_name,
                'lastMessagePreview' => $conversation->last_message_preview,
                'lastMessageAt' => $conversation->last_message_at?->toIso8601String(),
                'unreadCount' => $conversation->unread_count,
            ],
            'message' => [
                'id' => $whatsappMessage->id,
                'direction' => 'outbound',
                'source' => 'echo',
                'body' => $whatsappMessage->body,
                'mediaType' => $whatsappMessage->media_type,
                'status' => $whatsappMessage->status,
                'createdAt' => $whatsappMessage->created_at?->toIso8601String(),
            ],
        ]);
    }

    private function logRawIfEnabled(string $field, array $value): void
    {
        if (config('services.whatsapp_cloud.log_raw_webhooks')) {
            Log::debug("WhatsappWebhookController: payload grezzo campo \"{$field}\"", ['value' => $value]);
        }
    }

    private function normalizePhone(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }

    private function broadcast(int $tenantId, string $type, array $payload): void
    {
        broadcast(new WhatsappEvent($tenantId, $type, $payload));
    }
}
