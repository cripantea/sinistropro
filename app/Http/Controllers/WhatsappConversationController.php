<?php

namespace App\Http\Controllers;

use App\Models\WhatsappConversation;
use App\Services\WhatsappServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappConversationController extends Controller
{
    public function __construct(private WhatsappServiceClient $client)
    {
    }

    public function index(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $conversations = WhatsappConversation::where('tenant_id', $tenantId)
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->get()
            ->map(fn (WhatsappConversation $c) => $this->conversationPayload($c));

        return response()->json(['conversations' => $conversations]);
    }

    public function messages(WhatsappConversation $conversation): JsonResponse
    {
        abort_unless($conversation->tenant_id === auth()->user()->tenant_id, 403);

        $conversation->update(['unread_count' => 0]);

        $messages = $conversation->messages()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => $this->messagePayload($m));

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $authed = auth()->user();
        abort_unless($conversation->tenant_id === $authed->tenant_id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        $result = $this->client->sendMessage($authed->tenant_id, $conversation->phone_number, $data['body']);

        $message = $conversation->messages()->create([
            'tenant_id'     => $authed->tenant_id,
            'user_id'       => $authed->id,
            'direction'     => 'outbound',
            'body'          => $data['body'],
            'wa_message_id' => $result['id'] ?? null,
            'status'        => ($result['ok'] ?? false) ? 'sent' : 'failed',
        ]);

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => str($data['body'])->limit(200)->toString(),
        ]);

        return response()->json(['message' => $this->messagePayload($message->load('user:id,name'))]);
    }

    private function conversationPayload(WhatsappConversation $c): array
    {
        return [
            'id'                 => $c->id,
            'phoneNumber'        => $c->phone_number,
            'contactName'        => $c->contact_name,
            'lastMessagePreview' => $c->last_message_preview,
            'lastMessageAt'      => $c->last_message_at?->toIso8601String(),
            'unreadCount'        => $c->unread_count,
        ];
    }

    private function messagePayload($m): array
    {
        return [
            'id'        => $m->id,
            'direction' => $m->direction,
            'body'      => $m->body,
            'mediaType' => $m->media_type,
            'status'    => $m->status,
            'userName'  => $m->user?->name,
            'createdAt' => $m->created_at?->toIso8601String(),
        ];
    }
}
