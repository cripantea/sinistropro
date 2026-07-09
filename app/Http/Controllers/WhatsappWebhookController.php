<?php

namespace App\Http\Controllers;

use App\Events\WhatsappEvent;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsappWebhookController extends Controller
{
    /** Codici WAMessageStatus di Baileys: ERROR=0, PENDING=1, SERVER_ACK=2, DELIVERY_ACK=3, READ=4, PLAYED=5. */
    private const ACK_ERROR = 0;
    private const ACK_READ = 4;
    private const ACK_DELIVERED = 3;

    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'tenantId' => ['required', 'integer'],
            'event'    => ['required', 'string'],
            'data'     => ['nullable', 'array'],
        ]);

        $tenantId = (int) $validated['tenantId'];
        $data = $validated['data'] ?? [];

        match ($validated['event']) {
            'qr' => $this->handleQr($tenantId, $data),
            'ready' => $this->handleReady($tenantId, $data),
            'state' => $this->handleState($tenantId, $data),
            'status' => $this->touchSession($tenantId),
            'message' => $this->handleMessage($tenantId, $data),
            'ack' => $this->handleAck($tenantId, $data),
            'stopped' => $this->handleStopped($tenantId),
            default => Log::info('WhatsappWebhookController: evento non gestito', [
                'tenantId' => $tenantId,
                'event'    => $validated['event'],
            ]),
        };

        return response()->json(['ok' => true]);
    }

    private function handleQr(int $tenantId, array $data): void
    {
        WhatsappSession::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'status'        => 'qr',
                'qr_code'       => $data['qrcode'] ?? null,
                'last_event_at' => now(),
            ]
        );

        $this->broadcast($tenantId, 'qr', ['qrCode' => $data['qrcode'] ?? null]);
    }

    private function handleReady(int $tenantId, array $data): void
    {
        WhatsappSession::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'status'             => 'connected',
                'phone_number'       => $data['phoneNumber'] ?? null,
                'qr_code'            => null,
                'last_connected_at'  => now(),
                'last_event_at'      => now(),
            ]
        );

        $this->broadcast($tenantId, 'ready', ['phoneNumber' => $data['phoneNumber'] ?? null]);
    }

    private function handleState(int $tenantId, array $data): void
    {
        $state = $data['state'] ?? null;

        $status = match (true) {
            $state === 'CONNECTED' => 'connected',
            in_array($state, ['DISCONNECTED', 'CONFLICT', 'UNPAIRED', 'UNLAUNCHED', 'TIMEOUT'], true) => 'disconnected',
            default => null,
        };

        $session = WhatsappSession::firstOrCreate(['tenant_id' => $tenantId]);
        $session->last_event_at = now();
        if ($status !== null) {
            $session->status = $status;
        }
        $session->save();

        $this->broadcast($tenantId, 'state', ['state' => $state]);
    }

    private function touchSession(int $tenantId): void
    {
        WhatsappSession::firstOrCreate(['tenant_id' => $tenantId])
            ->forceFill(['last_event_at' => now()])
            ->save();
    }

    private function handleStopped(int $tenantId): void
    {
        WhatsappSession::updateOrCreate(
            ['tenant_id' => $tenantId],
            ['status' => 'disconnected', 'qr_code' => null, 'last_event_at' => now()]
        );

        $this->broadcast($tenantId, 'stopped', []);
    }

    private function handleMessage(int $tenantId, array $data): void
    {
        $message = $data['message'] ?? null;
        if (! is_array($message)) {
            Log::warning('WhatsappWebhookController: payload messaggio inatteso', ['tenantId' => $tenantId, 'data' => $data]);
            return;
        }

        $phoneNumber = $this->normalizePhone((string) ($message['from'] ?? ''));
        if ($phoneNumber === '') {
            Log::warning('WhatsappWebhookController: impossibile determinare il numero mittente', ['tenantId' => $tenantId, 'message' => $message]);
            return;
        }

        $conversation = WhatsappConversation::firstOrCreate(
            ['tenant_id' => $tenantId, 'phone_number' => $phoneNumber],
            ['contact_name' => $message['notifyName'] ?? $message['sender']['pushname'] ?? null]
        );

        if (! $conversation->contact_name && ($name = $message['notifyName'] ?? null)) {
            $conversation->contact_name = $name;
        }

        $body = $message['body'] ?? null;
        $isMedia = ($message['isMedia'] ?? false) || ($message['type'] ?? 'chat') !== 'chat';

        $whatsappMessage = WhatsappMessage::create([
            'tenant_id'                => $tenantId,
            'whatsapp_conversation_id' => $conversation->id,
            'direction'                => 'inbound',
            'body'                     => $body,
            'media_type'               => $isMedia ? ($message['type'] ?? null) : null,
            'media_mime_type'          => $isMedia ? ($message['mimetype'] ?? null) : null,
            'wa_message_id'            => is_string($message['id'] ?? null) ? $message['id'] : null,
            'status'                   => 'received',
        ]);

        $conversation->last_message_at = now();
        $conversation->last_message_preview = Str::limit((string) ($body ?? ($isMedia ? '[media]' : '')), 200);
        $conversation->increment('unread_count');
        $conversation->save();

        $this->broadcast($tenantId, 'message', [
            'conversation' => [
                'id'                  => $conversation->id,
                'phoneNumber'         => $conversation->phone_number,
                'contactName'         => $conversation->contact_name,
                'lastMessagePreview'  => $conversation->last_message_preview,
                'lastMessageAt'       => $conversation->last_message_at?->toIso8601String(),
                'unreadCount'         => $conversation->unread_count,
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

    private function handleAck(int $tenantId, array $data): void
    {
        $ack = $data['ack'] ?? null;
        $waMessageId = is_array($ack) ? ($ack['id'] ?? null) : null;
        $ackCode = is_array($ack) ? ($ack['ack'] ?? null) : null;

        if (! is_string($waMessageId) || $ackCode === null) {
            Log::info('WhatsappWebhookController: payload ack non riconosciuto', ['tenantId' => $tenantId, 'data' => $data]);
            return;
        }

        $message = WhatsappMessage::where('tenant_id', $tenantId)
            ->where('wa_message_id', $waMessageId)
            ->first();

        if (! $message) {
            return;
        }

        $status = match (true) {
            (int) $ackCode === self::ACK_ERROR => 'failed',
            (int) $ackCode >= self::ACK_READ => 'read',
            (int) $ackCode >= self::ACK_DELIVERED => 'delivered',
            default => 'sent',
        };

        $message->update(['status' => $status]);

        $this->broadcast($tenantId, 'ack', [
            'messageId' => $message->id,
            'status'    => $status,
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
