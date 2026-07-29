<?php

namespace App\Jobs;

use App\Mail\AutomazioneNotificaMail;
use App\Models\Automation;
use App\Models\Pratica;
use App\Services\TenantMailerResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExecuteAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 30;

    // Chiavi dei custom_fields scansionate per ogni dato del cliente
    private const EMAIL_KEYS  = ['email', 'email_cliente', 'email_contatto', 'email_assicurato'];
    private const PHONE_KEYS  = ['telefono', 'telefono_cliente', 'cellulare', 'whatsapp', 'phone'];
    private const NAME_KEYS   = ['nome_cliente', 'nome_assicurato', 'nome', 'cliente', 'controparte', 'ragione_sociale'];

    public function __construct(
        public readonly Pratica    $pratica,
        public readonly Automation $automation,
    ) {
        $this->onQueue('automations');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function handle(TenantMailerResolver $mailer): void
    {
        $pratica = Pratica::with([
            'tenant',
            'utenteCreatore',
            'currentStatus',
            'allegati',
            'ispezioni' => fn ($q) => $q->latest()->limit(1)->with('assegnatoa'),
        ])->findOrFail($this->pratica->id);

        $automation = $this->automation->loadMissing('documentCategories');

        // Carica gli utenti del tenant per risolvere i destinatari user
        $tenantUsers = \App\Models\User::where('tenant_id', $pratica->tenant_id)
            ->whereIn('id', $this->collectUserIds($automation))
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        // 1. Risolvi destinatari TO (nuovo sistema o legacy)
        $recipientsTo = $this->resolveRecipientsTo($pratica, $automation, $tenantUsers);

        if (empty($recipientsTo) && $automation->channel !== 'whatsapp') {
            Log::warning('ExecuteAutomationJob: nessun destinatario TO trovato, skip', [
                'pratica_id'    => $pratica->id,
                'automation_id' => $automation->id,
            ]);
            return;
        }

        // 2. Risolvi CC
        $ccEmails = $this->resolveCcEmails($automation, $tenantUsers);

        // 3. Genera link S3
        $documentLinks = $this->generateDocumentLinks($pratica, $automation);

        // 4. Invia a ogni destinatario TO
        foreach ($recipientsTo as $recipient) {
            $compiledMessage = $this->compileTemplate(
                $automation->message_template,
                $pratica,
                $recipient,
                $documentLinks
            );
            $this->sendViaChannel($automation->channel, $recipient, $compiledMessage, $pratica, $documentLinks, $ccEmails, $mailer);
        }

        Log::info('ExecuteAutomationJob: eseguito con successo', [
            'pratica_id'    => $pratica->id,
            'automation_id' => $automation->id,
            'to_count'      => count($recipientsTo),
            'cc_count'      => count($ccEmails),
        ]);
    }

    private function collectUserIds(\App\Models\Automation $automation): array
    {
        $ids = [];
        foreach ($automation->recipients_to ?? [] as $r) {
            if (($r['type'] ?? '') === 'user' && isset($r['user_id'])) $ids[] = (int) $r['user_id'];
        }
        foreach ($automation->recipients_cc ?? [] as $r) {
            if (isset($r['user_id'])) $ids[] = (int) $r['user_id'];
        }
        return array_unique($ids);
    }

    private function resolveRecipientsTo(Pratica $pratica, \App\Models\Automation $automation, \Illuminate\Support\Collection $users): array
    {
        $spec = $automation->recipients_to;

        // Fallback al vecchio campo recipient
        if (empty($spec)) {
            $resolved = $this->resolveRecipient($pratica, $automation->recipient ?? 'cliente');
            return $resolved['email'] ? [$resolved] : [];
        }

        $result = [];
        foreach ($spec as $r) {
            $type = $r['type'] ?? '';
            if ($type === 'cliente') {
                $resolved = $this->resolveCliente($pratica);
                if ($resolved['email']) $result[] = $resolved;
            } elseif ($type === 'user' && isset($r['user_id'])) {
                $user = $users->get((int) $r['user_id']);
                if ($user?->email) {
                    $result[] = ['email' => $user->email, 'name' => $user->name, 'phone' => null];
                }
            }
        }
        return $result;
    }

    private function resolveCcEmails(\App\Models\Automation $automation, \Illuminate\Support\Collection $users): array
    {
        $emails = [];
        foreach ($automation->recipients_cc ?? [] as $r) {
            if (isset($r['user_id'])) {
                $email = $users->get((int) $r['user_id'])?->email;
                if ($email) $emails[] = $email;
            }
        }
        return array_unique($emails);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Risoluzione destinatario
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ritorna ['email' => ?string, 'phone' => ?string, 'name' => ?string].
     */
    private function resolveRecipient(Pratica $pratica, string $recipient): array
    {
        return match ($recipient) {
            'cliente' => $this->resolveCliente($pratica),
            'gestore' => $this->resolveGestore($pratica),
            'perito'  => $this->resolvePerito($pratica),
            default   => ['email' => null, 'phone' => null, 'name' => null],
        };
    }

    private function resolveCliente(Pratica $pratica): array
    {
        $fields = $pratica->custom_fields ?? [];

        return [
            'email' => $this->scanFields($fields, self::EMAIL_KEYS),
            'phone' => $this->scanFields($fields, self::PHONE_KEYS),
            'name'  => $this->scanFields($fields, self::NAME_KEYS),
        ];
    }

    private function resolveGestore(Pratica $pratica): array
    {
        $user = $pratica->utenteCreatore;

        return [
            'email' => $user?->email,
            'phone' => null, // Il modello User non ha un campo telefono
            'name'  => $user?->name,
        ];
    }

    private function resolvePerito(Pratica $pratica): array
    {
        // Usa l'ispezione più recente con un perito assegnato
        $ispezione = $pratica->ispezioni
            ->whereNotNull('assegnato_a_user_id')
            ->first();

        $perito = $ispezione?->assegnatoa;

        return [
            'email' => $perito?->email,
            'phone' => null, // Il modello User non ha un campo telefono
            'name'  => $perito?->name,
        ];
    }

    /**
     * Cerca il primo valore non-null/non-vuoto tra le chiavi fornite.
     */
    private function scanFields(array $fields, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Generazione link S3 temporanei
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ritorna array di ['nome_file' => string, 'url' => string].
     * Filtra gli allegati della pratica per le categorie collegate all'automazione.
     * Se l'automazione non ha categorie collegate, non genera nessun link.
     */
    private function generateDocumentLinks(Pratica $pratica, Automation $automation): array
    {
        $categoryIds = $automation->documentCategories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return [];
        }

        $allegati = $pratica->allegati
            ->whereIn('document_category_id', $categoryIds)
            ->whereNotNull('s3_key');

        $links = [];
        foreach ($allegati as $allegato) {
            try {
                $url = Storage::disk('s3')->temporaryUrl(
                    $allegato->s3_key,
                    now()->addDays(7)
                );
                $links[] = ['nome_file' => $allegato->nome_file, 'url' => $url];
            } catch (\Throwable $e) {
                // File non trovato su S3 (es. record demo senza file reale): skip silenzioso
                Log::warning('ExecuteAutomationJob: S3 temporaryUrl fallita', [
                    'allegato_id' => $allegato->id,
                    's3_key'      => $allegato->s3_key,
                    'errore'      => $e->getMessage(),
                ]);
            }
        }

        return $links;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Compilazione template
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sostituisce i placeholder nel message_template con i valori reali.
     *
     * Placeholder supportati:
     *   {numero_pratica}   → ID della pratica
     *   {nome_cliente}     → nome del destinatario risolto
     *   {stato_corrente}   → nome dello stato corrente della pratica
     *   {nome_tenant}      → nome del tenant
     *   {link_documenti}   → lista URL dei file S3 (una riga per file)
     *   {campi_custom.*}   → qualsiasi campo custom, es. {campi_custom.numero_sinistro}
     */
    private function compileTemplate(
        string $template,
        Pratica $pratica,
        array $recipient,
        array $documentLinks
    ): string {
        $replacements = [
            '{numero_pratica}'  => (string) $pratica->id,
            '{nome_cliente}'    => $recipient['name'] ?? 'Cliente',
            '{stato_corrente}'  => $pratica->currentStatus?->name ?? '',
            '{nome_tenant}'     => $pratica->tenant?->name ?? '',
            '{link_documenti}'  => '',
        ];

        // Campi custom dinamici: {campi_custom.nome_campo}
        foreach ($pratica->custom_fields ?? [] as $key => $value) {
            $replacements["{campi_custom.{$key}}"] = (string) $value;
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Invio sul canale
    // ─────────────────────────────────────────────────────────────────────────

    private function sendViaChannel(
        string $channel,
        array $recipient,
        string $compiledMessage,
        Pratica $pratica,
        array $documentLinks,
        array $ccEmails,
        TenantMailerResolver $mailer
    ): void {
        if (in_array($channel, ['email', 'both'], true)) {
            $this->sendEmail($recipient, $compiledMessage, $pratica, $documentLinks, $ccEmails, $mailer);
        }

        if (in_array($channel, ['whatsapp', 'both'], true)) {
            $this->sendWhatsapp($recipient, $compiledMessage, $pratica);
        }
    }

    private function sendEmail(array $recipient, string $compiledMessage, Pratica $pratica, array $documentLinks, array $ccEmails, TenantMailerResolver $mailer): void
    {
        if (! $recipient['email']) {
            Log::warning('ExecuteAutomationJob: email vuota, invio saltato', [
                'pratica_id'    => $pratica->id,
                'automation_id' => $this->automation->id,
            ]);
            return;
        }

        $subject = "Sinistro #{$pratica->id} — {$pratica->currentStatus?->name}";

        $mailer->send(
            $pratica->tenant,
            $recipient['email'],
            new AutomazioneNotificaMail(
                emailSubject:  $subject,
                compiledBody:  $compiledMessage,
                tenantName:    $pratica->tenant?->name ?? '',
                documentLinks: $documentLinks,
            ),
            $ccEmails
        );
    }

    private function sendWhatsapp(array $recipient, string $compiledMessage, Pratica $pratica): void
    {
        // TODO: integrare con provider WhatsApp (Twilio, Meta Cloud API, ecc.)
        // Il numero di telefono del destinatario è in $recipient['phone'].
        // Per utenti interni (gestore/perito) il telefono non è sul modello User:
        // aggiungere colonna `phone` alla tabella `users` quando si integra il provider.
        Log::info('ExecuteAutomationJob: WhatsApp placeholder', [
            'pratica_id'    => $pratica->id,
            'automation_id' => $this->automation->id,
            'phone'         => $recipient['phone'] ?? 'N/D',
            'message_len'   => strlen($compiledMessage),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteAutomationJob: job fallito definitivamente', [
            'pratica_id'    => $this->pratica->id,
            'automation_id' => $this->automation->id,
            'errore'        => $exception->getMessage(),
        ]);
    }
}
