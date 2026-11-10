<?php

namespace App\Services;

use App\Models\WhatsappConversation;
use App\Models\WhatsappSession;
use App\Models\WhatsappTemplate;
use App\Support\NumeroWhatsapp;

/**
 * Invio WhatsApp dal numero dell'agenzia (automazioni e chat), con il messaggio salvato nella conversazione.
 * Con un template approvato parte il template (arriva anche fuori dalle 24 ore), altrimenti il testo libero.
 */
class WhatsappInvio
{
    public function __construct(private readonly WhatsappCloudApiClient $client) {}

    public static function sessione(int $tenantId): ?WhatsappSession
    {
        return WhatsappSession::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }

    /** Il cliente ha scritto nelle ultime 24 ore: si può mandare testo libero. */
    public static function finestraAperta(WhatsappConversation $conversazione): bool
    {
        return $conversazione->messages()->where('direction', 'inbound')
            ->where(fn ($q) => $q->where('wa_timestamp', '>=', now()->subDay())->orWhere(fn ($q) => $q->whereNull('wa_timestamp')->where('created_at', '>=', now()->subDay())))
            ->exists();
    }

    /**
     * @param  array<int, string>  $valori  valori per {{1}}, {{2}}… del template
     * @return array{ok: bool, errore: ?string, messaggio: ?\App\Models\WhatsappMessage}
     */
    public function invia(int $tenantId, string $telefono, string $testo, ?WhatsappTemplate $template = null, array $valori = [], ?int $praticaId = null, ?int $userId = null, ?string $nome = null): array
    {
        $numero = NumeroWhatsapp::normalizza($telefono);
        if (! $numero) {
            return ['ok' => false, 'errore' => 'Numero di telefono non valido.', 'messaggio' => null];
        }
        $sessione = self::sessione($tenantId);
        if (! $sessione) {
            return ['ok' => false, 'errore' => 'Nessun numero WhatsApp collegato.', 'messaggio' => null];
        }

        $usaTemplate = $template && $template->approvato();
        if ($usaTemplate) {
            $valori = array_map(fn ($v) => trim((string) $v) !== '' ? (string) $v : '-', array_slice(array_pad($valori, $template->variable_count, ''), 0, $template->variable_count));
            $risultato = $this->client->sendTemplate($sessione, $numero, $template->name, $template->language, [
                ['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $v], $valori)],
            ]);
            $testo = $template->rendi($valori);
        } else {
            $risultato = $this->client->sendText($sessione, $numero, $testo);
        }

        $waId = $risultato['messages'][0]['id'] ?? null;
        $errore = $risultato['error'] ?? null;
        $errore = $waId ? null : ((is_array($errore) ? ($errore['message'] ?? null) : $errore) ?: 'Invio non riuscito.');

        $conversazione = WhatsappConversation::acrossAllTenants()->firstOrCreate(
            ['tenant_id' => $tenantId, 'phone_number' => $numero],
            ['pratica_id' => $praticaId, 'contact_name' => $nome],
        );
        $messaggio = $conversazione->messages()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'direction' => 'outbound',
            'source' => 'api',
            'body' => $testo,
            'wa_message_id' => $waId,
            'wa_timestamp' => now(),
            'status' => $waId ? 'sent' : 'failed',
        ]);
        $conversazione->update(['last_message_at' => now(), 'last_message_preview' => str($testo)->limit(200)->toString()]);

        return ['ok' => (bool) $waId, 'errore' => $errore, 'messaggio' => $messaggio];
    }
}
