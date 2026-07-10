<?php

namespace App\Http\Controllers;

use App\Events\WhatsappEvent;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
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
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (! is_string($phoneNumberId)) {
                    continue;
                }

                $session = WhatsappSession::where('phone_number_id', $phoneNumberId)
                    ->where('status', 'active')
                    ->first();

                if (! $session) {
                    Log::warning('WhatsappWebhookController: nessun tenant collegato a questo numero', ['phoneNumberId' => $phoneNumberId]);
                    continue;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    $this->handleMessage($session, $value['contacts'] ?? [], $message);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->handleStatus($session, $status);
                }
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

        $whatsappMessage = WhatsappMessage::create([
            'tenant_id'                => $session->tenant_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction'                => 'inbound',
            'body'                     => $body,
            'media_type'               => $mediaType,
            'wa_message_id'            => $waMessageId,
            'status'                   => 'received',
        ]);

        $conversation->last_message_at = now();
        $conversation->last_message_preview = Str::limit((string) ($body ?? ($mediaType ? '[media]' : '')), 200);
        $conversation->increment('unread_count');
        $conversation->save();

        $this->broadcast($session->tenant_id, 'message', [
            'conversation' => [
                'id'                 => $conversation->id,
                'phoneNumber'        => $conversation->phone_number,
                'contactName'        => $conversation->contact_name,
                'lastMessagePreview' => $conversation->last_message_preview,
                'lastMessageAt'      => $conversation->last_message_at?->toIso8601String(),
                'unreadCount'        => $conversation->unread_count,
            ],
            'message' => [
                'id'        => $whatsappMessage->id,
                'direction' => 'inbound',
                'body'      => $whatsappMessage->body,
                'mediaType' => $whatsappMessage->media_type,
                'status'    => $whatsappMessage->status,
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
            'status'    => $newStatus,
        ]);
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
