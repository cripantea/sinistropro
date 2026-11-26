<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailMessage;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * L'agenzia elimina da SinistroPro email e messaggi WhatsApp (es. quelli mandati per prova).
 * Si tolgono solo da SinistroPro: chi li ha ricevuti li ha già, e la casella Outlook/Gmail non cambia.
 * Sempre e solo i messaggi dell'agenzia in cui si lavora.
 */
class EliminaMessaggiController extends Controller
{
    /** Email della casella (posta e corrispondenza del sinistro). */
    public function email(int $id): JsonResponse
    {
        $messaggio = EmailMessage::acrossAllTenants()->with('attachments')->findOrFail($id);
        $this->dellAgenzia($messaggio->tenant_id);

        $thread = $messaggio->thread;
        foreach ($messaggio->attachments as $allegato) {
            if ($allegato->s3_key) {
                rescue(fn () => Storage::disk('s3')->delete($allegato->s3_key), report: false);
            }
        }
        $messaggio->delete(); // allegati: cascade nel DB

        if ($thread) {
            $ultimo = EmailMessage::acrossAllTenants()->where('email_thread_id', $thread->id)->orderByDesc('email_timestamp')->first();
            $ultimo
                ? $thread->update([
                    'last_message_at' => $ultimo->email_timestamp,
                    'last_message_preview' => Str::limit(trim(strip_tags((string) ($ultimo->body_text ?: $ultimo->body_html))), 200),
                ])
                : $thread->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** Email partite dalle automazioni (registro email). */
    public function emailAutomatica(int $id): JsonResponse
    {
        $log = EmailLog::acrossAllTenants()->findOrFail($id);
        $this->dellAgenzia($log->tenant_id);
        $log->delete();

        return response()->json(['ok' => true]);
    }

    public function whatsapp(int $id): JsonResponse
    {
        $messaggio = WhatsappMessage::acrossAllTenants()->findOrFail($id);
        $this->dellAgenzia($messaggio->tenant_id);

        $conversazione = WhatsappConversation::acrossAllTenants()->find($messaggio->whatsapp_conversation_id);
        $messaggio->delete();

        if ($conversazione) {
            $ultimo = WhatsappMessage::acrossAllTenants()->where('whatsapp_conversation_id', $conversazione->id)
                ->orderByRaw('COALESCE(wa_timestamp, created_at) DESC')->first();
            $conversazione->update([
                'last_message_at' => $ultimo ? ($ultimo->wa_timestamp ?? $ultimo->created_at) : null,
                'last_message_preview' => $ultimo ? Str::limit((string) ($ultimo->body ?: ($ultimo->media_type ? '['.$ultimo->media_type.']' : '')), 200) : null,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function dellAgenzia(?int $tenantId): void
    {
        // 404 e non 403: non si rivela che il messaggio esiste in un'altra agenzia.
        abort_unless($tenantId !== null && (int) $tenantId === (int) TenantContext::id(), 404);
    }
}
