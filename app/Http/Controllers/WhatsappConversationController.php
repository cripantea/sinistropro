<?php

namespace App\Http\Controllers;

use App\Services\WhatsappInvio;
use App\Services\AutomationPlanner;
use App\Models\WhatsappTemplate;
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

    private const NAME_KEYS = ['nome', 'cliente', 'contraente', 'assicurato', 'nominativo'];

    public function __construct(private WhatsappCloudApiClient $client) {}

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
                'phoneNumber' => null,
                'sessionConnected' => $sessionConnected,
                'conversation' => null,
            ]);
        }

        $conversation = WhatsappConversation::firstOrCreate(
            ['tenant_id' => $authed->tenant_id, 'phone_number' => $phoneNumber],
            [
                'pratica_id' => $pratica->id,
                'contact_name' => $this->extractValue($fields, self::NAME_KEYS),
            ]
        );

        if ($conversation->pratica_id !== $pratica->id) {
            $conversation->update(['pratica_id' => $pratica->id]);
        }

        return response()->json([
            'phoneNumber' => $phoneNumber,
            'sessionConnected' => $sessionConnected,
            'conversation' => $this->conversationPayload($conversation),
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
            $digits = '39'.$digits;
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
            // Ora vera del messaggio: quelli importati dallo storico hanno created_at = giorno dell'import.
            ->orderByRaw('COALESCE(wa_timestamp, created_at)')
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => $this->messagePayload($m));

        return response()->json(['messages' => $messages] + $this->perScrivere($conversation));
    }

    /** Finestra di 24 ore e template approvati (già compilati coi dati del sinistro, se la chat ne ha uno). */
    private function perScrivere(WhatsappConversation $conversation): array
    {
        $pratica = $conversation->pratica_id ? Pratica::find($conversation->pratica_id) : null;
        $planner = app(AutomationPlanner::class);

        return [
            'finestraAperta' => WhatsappInvio::finestraAperta($conversation),
            'templates' => WhatsappTemplate::where('tenant_id', $conversation->tenant_id)->approvati()->orderBy('name')->get()
                ->map(fn (WhatsappTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'body' => $t->body,
                    'variableCount' => $t->variable_count,
                    'valori' => array_map(
                        fn ($codice) => $pratica && $codice !== '' ? trim($planner->compilePratica($codice, $pratica, $pratica->cliente?->nome)) : '',
                        $t->parametri(),
                    ),
                ])->values(),
        ];
    }

    public function store(Request $request, WhatsappConversation $conversation): JsonResponse
    {
        $authed = auth()->user();
        abort_unless($conversation->tenant_id === $authed->tenant_id, 403);

        $data = $request->validate([
            'body' => ['required_without:template_id', 'nullable', 'string', 'max:4096'],
            'template_id' => ['nullable', 'integer'],
            'params' => ['nullable', 'array', 'max:20'],
            'params.*' => ['nullable', 'string', 'max:500'],
        ]);

        $session = WhatsappSession::where('tenant_id', $authed->tenant_id)->where('status', 'active')->first();
        abort_unless($session, 409, 'Nessun numero WhatsApp collegato per questo tenant.');

        $template = null;
        if (! empty($data['template_id'])) {
            $template = WhatsappTemplate::where('tenant_id', $authed->tenant_id)->approvati()->find($data['template_id']);
            abort_unless($template, 422, 'Template non trovato o non ancora approvato da Meta.');
            $params = array_slice(array_pad($data['params'] ?? [], $template->variable_count, ''), 0, $template->variable_count);
            foreach ($params as $i => $v) {
                abort_if(trim((string) $v) === '', 422, 'Compila {{'.($i + 1).'}}: WhatsApp non accetta variabili vuote.');
            }
        }

        $esito = app(WhatsappInvio::class)->invia(
            $authed->tenant_id, $conversation->phone_number, (string) ($data['body'] ?? ''), $template, $params ?? [], $conversation->pratica_id, $authed->id,
        );

        return response()->json([
            'message' => $this->messagePayload($esito['messaggio']->load('user:id,name')),
            'errore' => $esito['ok'] ? null : $esito['errore'],
        ]);
    }

    private function conversationPayload(WhatsappConversation $c): array
    {
        return [
            'id' => $c->id,
            'phoneNumber' => $c->phone_number,
            'contactName' => $c->contact_name,
            'lastMessagePreview' => $c->last_message_preview,
            'lastMessageAt' => $c->last_message_at?->toIso8601String(),
            'unreadCount' => $c->unread_count,
        ];
    }

    private function messagePayload($m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'source' => $m->source,
            'body' => $m->body,
            'mediaType' => $m->media_type,
            'mediaUrl' => $m->mediaUrl(),
                'mediaMime' => $m->media_mime_type,
                'mediaFilename' => $m->media_filename,
            'status' => $m->status,
            'userName' => $m->user?->name,
            'createdAt' => ($m->wa_timestamp ?? $m->created_at)?->toIso8601String(),
        ];
    }
}
