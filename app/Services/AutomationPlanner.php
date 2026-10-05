<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\Cliente;
use App\Models\Contatto;
use App\Models\Pratica;
use App\Models\TenantStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Calcola COSA farebbe un'automazione (messaggio compilato, destinatari, CC, documenti)
 * senza inviare nulla. È la stessa logica usata dai job al momento dell'invio, così
 * l'anteprima mostrata all'utente per la conferma coincide con ciò che parte davvero.
 *
 * Struttura di un destinatario: ['key', 'kind', 'name', 'email', 'phone'].
 */
class AutomationPlanner
{
    private const EMAIL_KEYS = ['email', 'email_cliente', 'email_contatto', 'email_assicurato'];
    private const PHONE_KEYS = ['telefono', 'telefono_cliente', 'cellulare', 'whatsapp', 'phone'];
    private const NAME_KEYS  = ['nome_cliente', 'nome_assicurato', 'nome', 'cliente', 'controparte', 'ragione_sociale'];

    // ─────────────────────────────────────────────────────────────────────────
    // Pratica
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array{status_id?: ?int, perito_contatto_id?: ?int, carrozzeria_contatto_id?: ?int}  $ctx
     *         Valori "in arrivo" non ancora salvati (l'anteprima precede il salvataggio).
     * @return array<string, mixed>
     */
    public function planPratica(Pratica $pratica, Automation $automation, array $ctx = []): array
    {
        $pratica->loadMissing([
            'tenant', 'utenteCreatore', 'currentStatus', 'allegati', 'cliente',
            'ispezioni' => fn ($q) => $q->latest()->limit(1)->with(['peritoContatto', 'carrozzeriaContatto', 'assegnatoa', 'carrozzeria']),
        ]);
        $automation->loadMissing('documentCategories');

        $recipients = $this->defaultRecipientsPratica($pratica, $automation, $ctx);
        $cc         = $this->defaultCc($automation);
        $links      = $this->documentLinks($pratica, $automation, preview: true);

        $statusName = ! empty($ctx['status_id'])
            ? (TenantStatus::find($ctx['status_id'])?->name ?? '')
            : ($pratica->currentStatus?->name ?? '');

        return [
            'id'         => $automation->id,
            'name'       => $automation->name,
            'channel'    => $automation->channel,
            'subject'    => "Sinistro #{$pratica->id} — {$statusName}",
            'recipients' => $recipients,
            'cc'         => $cc,
            'documents'  => array_column($links, 'nome_file'),
            'message'    => $this->compilePratica($automation->message_template, $pratica, $recipients[0]['name'] ?? null, $statusName),
        ];
    }

    /** Destinatari risolti per una pratica (anche quelli senza recapito: la UI li segnala). */
    public function defaultRecipientsPratica(Pratica $pratica, Automation $automation, array $ctx = []): array
    {
        $spec = $automation->recipients_to;
        if (empty($spec)) {
            $spec = [['type' => $automation->recipient ?: 'cliente']];
        }

        $userIds = collect($spec)->where('type', 'user')->pluck('user_id')->filter()->map(fn ($i) => (int) $i)->all();
        $users   = $userIds
            ? User::where('tenant_id', $pratica->tenant_id)->whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id')
            : collect();

        $out = [];
        foreach ($spec as $r) {
            $type = $r['type'] ?? '';
            $resolved = match ($type) {
                'cliente'     => $this->resolveCliente($pratica),
                'carrozzeria' => $this->resolveCarrozzeria($pratica, $ctx),
                'perito'      => $this->resolvePerito($pratica, $ctx),
                'gestore'     => $this->resolveGestore($pratica),
                'user'        => ($u = $users->get((int) ($r['user_id'] ?? 0))) ? ['email' => $u->email, 'phone' => null, 'name' => $u->name] : null,
                default       => null,
            };
            if ($resolved === null) {
                continue;
            }
            $out[] = $this->recipient($type, $resolved['name'], $resolved['email'], $resolved['phone'], $type === 'user' ? 'user'.($r['user_id'] ?? '') : $type);
        }

        // Stesso destinatario due volte (es. cliente + gestore con la stessa email): una sola.
        return collect($out)->unique(fn ($r) => strtolower((string) $r['email']) ?: $r['key'])->values()->all();
    }

    public function defaultCc(Automation $automation): array
    {
        $ids = collect($automation->recipients_cc ?? [])->pluck('user_id')->filter()->map(fn ($i) => (int) $i)->all();
        if (! $ids) {
            return [];
        }

        return User::where('tenant_id', $automation->tenant_id)->whereIn('id', $ids)->get(['name', 'email'])
            ->filter(fn ($u) => $u->email)
            ->map(fn ($u) => ['name' => $u->name, 'email' => $u->email])
            ->unique('email')->values()->all();
    }

    public function compilePratica(string $template, Pratica $pratica, ?string $recipientName, ?string $statusName = null): string
    {
        $replacements = [
            '{numero_pratica}' => (string) $pratica->id,
            '{nome_cliente}'   => $recipientName ?: 'Cliente',
            '{stato_corrente}' => $statusName ?? ($pratica->currentStatus?->name ?? ''),
            '{nome_tenant}'    => $pratica->tenant?->name ?? '',
            '{link_documenti}' => '',
        ];

        foreach ($pratica->custom_fields ?? [] as $key => $value) {
            $replacements["{campi_custom.{$key}}"] = is_scalar($value) ? (string) $value : '';
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Link temporanei ai documenti collegati all'automazione. In anteprima non si generano
     * URL firmati (costo S3 inutile): basta l'elenco dei file.
     *
     * @return array<int, array{nome_file: string, url: string}>
     */
    public function documentLinks(Pratica $pratica, Automation $automation, bool $preview = false): array
    {
        $categoryIds = $automation->documentCategories->pluck('id');
        if ($categoryIds->isEmpty()) {
            return [];
        }

        $allegati = $pratica->allegati->whereIn('document_category_id', $categoryIds)->whereNotNull('s3_key');

        $links = [];
        foreach ($allegati as $allegato) {
            if ($preview) {
                $links[] = ['nome_file' => $allegato->nome_file, 'url' => ''];
                continue;
            }
            try {
                $links[] = [
                    'nome_file' => $allegato->nome_file,
                    'url'       => Storage::disk('s3')->temporaryUrl($allegato->s3_key, now()->addDays(7)),
                ];
            } catch (\Throwable $e) {
                Log::warning('AutomationPlanner: S3 temporaryUrl fallita', ['allegato_id' => $allegato->id, 'errore' => $e->getMessage()]);
            }
        }

        return $links;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cliente (promemoria su campi data)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function planCliente(Cliente $cliente, Automation $automation, string $field): array
    {
        $cliente->loadMissing('tenant');
        $recipients = [$this->recipient('cliente', $cliente->nome, $this->filled($cliente->email), $this->filled($cliente->telefono), 'cliente')];

        return [
            'id'         => $automation->id,
            'name'       => $automation->name,
            'channel'    => $automation->channel,
            'subject'    => "Promemoria — {$cliente->nome}",
            'recipients' => $recipients,
            'cc'         => [],
            'documents'  => [],
            'message'    => $this->compileCliente($automation->message_template, $cliente, $cliente->custom_fields[$field] ?? null),
        ];
    }

    public function compileCliente(string $template, Cliente $cliente, ?string $fieldValue): string
    {
        $data = $fieldValue ? \Carbon\Carbon::parse($fieldValue)->format('d/m/Y') : '';
        $replacements = [
            '{nome_cliente}'  => $cliente->nome,
            '{nome_tenant}'   => $cliente->tenant?->name ?? '',
            '{data_scadenza}' => $data,
            '{campo_data}'    => $data,
        ];
        foreach ($cliente->custom_fields ?? [] as $key => $value) {
            $replacements["{cliente.{$key}}"] = is_scalar($value) ? (string) $value : '';
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Override decisi dall'utente nella finestra di conferma
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normalizza e sanifica gli override inviati dal client:
     *   { "<automationId>": { send: bool, recipients: [{name,email,phone}], cc: [email] } }
     *
     * @return array<int, array{send: bool, recipients: array, cc: array}>
     */
    public static function normalizeOverrides(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $automationId => $o) {
            if (! is_numeric($automationId) || ! is_array($o)) {
                continue;
            }

            $recipients = [];
            foreach (array_slice((array) ($o['recipients'] ?? []), 0, 25) as $r) {
                $email = filter_var(trim((string) ($r['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null;
                $phone = trim((string) ($r['phone'] ?? '')) ?: null;
                if (! $email && ! $phone) {
                    continue;
                }
                $recipients[] = [
                    'name'  => mb_substr(trim((string) ($r['name'] ?? '')), 0, 120) ?: null,
                    'email' => $email ? strtolower($email) : null,
                    'phone' => $phone ? mb_substr($phone, 0, 40) : null,
                ];
            }

            $cc = collect((array) ($o['cc'] ?? []))
                ->map(fn ($e) => filter_var(trim((string) (is_array($e) ? ($e['email'] ?? '') : $e)), FILTER_VALIDATE_EMAIL) ?: null)
                ->filter()->map('strtolower')->unique()->take(25)->values()->all();

            $out[(int) $automationId] = [
                'send'       => filter_var($o['send'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'recipients' => $recipients,
                'cc'         => $cc,
            ];
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Risoluzione destinatari
    // ─────────────────────────────────────────────────────────────────────────

    private function recipient(string $kind, ?string $name, ?string $email, ?string $phone, string $key): array
    {
        return ['key' => $key, 'kind' => $kind, 'name' => $name, 'email' => $email ?: null, 'phone' => $phone ?: null];
    }

    private function resolveCliente(Pratica $pratica): array
    {
        $fields  = $pratica->custom_fields ?? [];
        $cliente = $pratica->cliente;

        // L'anagrafica cliente ha la precedenza; i campi custom della pratica restano
        // come fallback per i tenant che non usano i clienti.
        return [
            'email' => $this->filled($cliente?->email) ?? $this->scanFields($fields, self::EMAIL_KEYS),
            'phone' => $this->filled($cliente?->telefono) ?? $this->scanFields($fields, self::PHONE_KEYS),
            'name'  => $this->filled($cliente?->nome) ?? $this->scanFields($fields, self::NAME_KEYS),
        ];
    }

    private function resolveGestore(Pratica $pratica): array
    {
        $user = $pratica->utenteCreatore;

        return ['email' => $user?->email, 'phone' => null, 'name' => $user?->name];
    }

    private function resolvePerito(Pratica $pratica, array $ctx = []): array
    {
        if (array_key_exists('perito_contatto_id', $ctx) && $ctx['perito_contatto_id']) {
            $c = Contatto::tipo('perito')->find($ctx['perito_contatto_id']);
            if ($c) {
                return ['email' => $c->email, 'phone' => $c->telefono, 'name' => $c->nome];
            }
        }

        $ispezione = $pratica->ispezioni->first(fn ($i) => $i->perito_contatto_id || $i->assegnato_a_user_id);
        $contatto  = $ispezione?->peritoContatto;
        $user      = $ispezione?->assegnatoa;

        return [
            'email' => $contatto?->email ?: $user?->email,
            'phone' => $contatto?->telefono,
            'name'  => $contatto?->nome ?? $user?->name,
        ];
    }

    private function resolveCarrozzeria(Pratica $pratica, array $ctx = []): array
    {
        if (array_key_exists('carrozzeria_contatto_id', $ctx) && $ctx['carrozzeria_contatto_id']) {
            $c = Contatto::tipo('carrozzeria')->find($ctx['carrozzeria_contatto_id']);
            if ($c) {
                return ['email' => $c->email, 'phone' => $c->telefono, 'name' => $c->nome];
            }
        }

        $ispezione = $pratica->ispezioni->first(fn ($i) => $i->carrozzeria_contatto_id || $i->carrozzeria_user_id);
        $contatto  = $ispezione?->carrozzeriaContatto;
        $user      = $ispezione?->carrozzeria;

        return [
            'email' => $contatto?->email ?: $user?->email,
            'phone' => $contatto?->telefono,
            'name'  => $contatto?->nome ?? $user?->name,
        ];
    }

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

    private function filled(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
