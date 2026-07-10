<?php

namespace App\Http\Controllers;

use App\Models\Pratica;
use App\Models\WhatsappConversation;
use App\Models\WhatsappSession;
use App\Services\WhatsappCloudApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappConversationController extends Controller
{
    /** Stesse convenzioni di nome campo usate nel resto dell'app (Automazioni, mockup wa.me). */
    private const PHONE_KEYS = ['telefono', 'tel', 'phone', 'cellulare', 'mobile', 'numero_tel'];
    private const NAME_KEYS  = ['nome', 'cliente', 'contraente', 'assicurato', 'nominativo'];

    public function __construct(private WhatsappCloudApiClient $client)
    {
    }

    /**
     * Risolve (creando se necessario) la conversazione WhatsApp collegata al
     * numero di telefono della pratica, così la chat può essere mostrata
     * filtrata direttamente nella pagina della pratica.
     */
    public function forPratica(Pratica $pratica): JsonResponse
    {
        $authed = auth()->user();
        abort_unless($pratica->tenant_id === $authed->tenant_id, 403);

        $fields = $pratica->custom_fields ?? [];
        $phoneNumber = $this->extractDigits($fields, self::PHONE_KEYS);

        $session = WhatsappSession::where('tenant_id', $authed->tenant_id)->first();
        $sessionConnected = $session?->status === 'active';

        if (! $phoneNumber) {
            return response()->json([
                'phoneNumber'      => null,
                'sessionConnected' => $sessionConnected,
                'conversation'     => null,
            ]);
        }

        $conversation = WhatsappConversation::firstOrCreate(
            ['tenant_id' => $authed->tenant_id, 'phone_number' => $phoneNumber],
            [
                'pratica_id'   => $pratica->id,
                'contact_name' => $this->extractValue($fields, self::NAME_KEYS),
            ]
        );

        if ($conversation->pratica_id !== $pratica->id) {
            $conversation->update(['pratica_id' => $pratica->id]);
        }

        return response()->json([
            'phoneNumber'      => $phoneNumber,
            'sessionConnected' => $sessionConnected,
            'conversation'     => $this->conversationPayload($conversation),
        ]);
    }

    private function extractValue(array $fields, array $keys): ?string
    {
        foreach ($fields as $key => $value) {
            $lower = strtolower((string) $key);
            foreach ($keys as $k) {
                if (str_contains($lower, $k) && trim((string) $value) !== '') {
                    return (string) $value;
                }
            }
        }
        return null;
    }

    private function extractDigits(array $fields, array $keys): ?string
    {
        $value = $this->extractValue($fields, $keys);
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '') {
            return null;
        }

        // I numeri italiani vengono spesso inseriti senza prefisso internazionale
        // (es. 3331234567), mentre WhatsApp usa sempre il formato internazionale
        // (393331234567): senza normalizzare, il numero non combacerebbe mai.
        if (strlen($digits) <= 10 && ! str_starts_with($digits, '39')) {
            $digits = '39' . $digits;
        }

        return $digits;
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

        $session = WhatsappSession::where('tenant_id', $authed->tenant_id)->where('status', 'active')->first();
        abort_unless($session, 409, 'Nessun numero WhatsApp collegato per questo tenant.');

        $result = $this->client->sendText($session->phone_number_id, $conversation->phone_number, $data['body']);
        $waMessageId = $result['messages'][0]['id'] ?? null;

        $message = $conversation->messages()->create([
            'tenant_id'     => $authed->tenant_id,
            'user_id'       => $authed->id,
            'direction'     => 'outbound',
            'body'          => $data['body'],
            'wa_message_id' => $waMessageId,
            'status'        => $waMessageId ? 'sent' : 'failed',
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
