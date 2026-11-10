<?php

namespace App\Support;

use App\Models\Automation;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validazione e salvataggio delle automazioni, in comune tra superadmin e Impostazioni dell'agenzia.
 * Tutto ciò che si riferisce a stati, utenti, campi e categorie deve appartenere all'agenzia.
 */
class RegoleAutomazione
{
    public const DESTINATARI = ['cliente', 'carrozzeria', 'perito', 'gestore', 'user'];

    /** Valida la richiesta e restituisce i dati già normalizzati per il trigger scelto. */
    public static function valida(Request $request, Tenant $tenant): array
    {
        $trigger = (string) $request->input('trigger_type');
        $campiPratica = collect($tenant->getCustomFieldsSchema())->where('type', 'date')->pluck('name')->push('data_appuntamento')->all();
        $campiCliente = collect($tenant->getClienteCustomFieldsSchema())->where('type', 'date')->pluck('name')->all();
        $utenti = Rule::exists('users', 'id')->where('tenant_id', $tenant->id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', Rule::in(array_keys(Automation::TRIGGER))],
            'tenant_status_id' => ['nullable', 'integer', Rule::exists('tenant_statuses', 'id')->where('tenant_id', $tenant->id)],
            'watched_field' => [
                Rule::requiredIf(in_array($trigger, ['date_field', 'date_reminder', 'cliente_date_field'], true)),
                'nullable', 'string',
                Rule::in($trigger === 'cliente_date_field' ? $campiCliente : $campiPratica),
            ],
            'days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'offset_days' => ['nullable', 'integer', 'min:-365', 'max:365'],
            'ripeti' => ['boolean'],
            'channel' => ['required', Rule::in(['email', 'whatsapp', 'both'])],
            'subject' => ['nullable', 'string', 'max:200'],
            'recipients_to' => ['nullable', 'array', 'max:30'],
            'recipients_to.*.type' => ['required', Rule::in(self::DESTINATARI)],
            'recipients_to.*.user_id' => ['nullable', 'integer', $utenti],
            'recipients_cc' => ['nullable', 'array', 'max:30'],
            'recipients_cc.*.type' => ['required', Rule::in(['user'])],
            'recipients_cc.*.user_id' => ['required', 'integer', $utenti],
            'message_template' => ['required', 'string', 'max:5000'],
            'document_category_ids' => ['nullable', 'array'],
            'document_category_ids.*' => ['integer', Rule::exists('document_categories', 'id')],
            'is_active' => ['boolean'],
        ], [
            'watched_field.required' => 'Scegli la data da osservare.',
            'watched_field.in' => 'Questa data non esiste più tra i campi dell\'agenzia.',
            'message_template.required' => 'Scrivi il messaggio.',
            'recipients_to.*.user_id.exists' => 'Uno dei colleghi scelti non fa parte dell\'agenzia.',
            'recipients_cc.*.user_id.exists' => 'Uno dei colleghi in copia non fa parte dell\'agenzia.',
        ]);

        $destinatari = collect($data['recipients_to'] ?? [])
            ->map(fn ($r) => $r['type'] === 'user' ? ['type' => 'user', 'user_id' => (int) ($r['user_id'] ?? 0)] : ['type' => $r['type']])
            ->filter(fn ($r) => $r['type'] !== 'user' || $r['user_id'] > 0)
            ->unique(fn ($r) => $r['type'].':'.($r['user_id'] ?? ''))->values()->all();

        if ($trigger === 'status_duration') {
            if (empty($data['tenant_status_id'])) {
                throw ValidationException::withMessages(['tenant_status_id' => 'Scegli lo stato in cui il sinistro è fermo.']);
            }
            if ((int) ($data['offset_days'] ?? 0) < 1) {
                throw ValidationException::withMessages(['offset_days' => 'Indica dopo quanti giorni (almeno 1).']);
            }
        }

        // Il promemoria su data del cliente va sempre al cliente: lì i destinatari non si scelgono.
        if ($trigger !== 'cliente_date_field' && $destinatari === []) {
            throw ValidationException::withMessages(['recipients_to' => 'Scegli almeno un destinatario.']);
        }

        return [
            'name' => $data['name'],
            'trigger_type' => $trigger,
            'tenant_status_id' => in_array($trigger, ['status', 'status_duration'], true) ? ($data['tenant_status_id'] ?? null) : null,
            'watched_field' => in_array($trigger, ['date_field', 'date_reminder', 'cliente_date_field'], true) ? $data['watched_field'] : null,
            'days_before' => $trigger === 'cliente_date_field' ? (int) ($data['days_before'] ?? 0) : 0,
            'offset_days' => in_array($trigger, ['date_reminder', 'status_duration'], true) ? (int) ($data['offset_days'] ?? 0) : 0,
            'ripeti' => $trigger === 'status_duration' && ! empty($data['ripeti']),
            'channel' => $data['channel'],
            'subject' => trim((string) ($data['subject'] ?? '')) ?: null,
            'recipient' => 'cliente',
            'recipients_to' => $trigger === 'cliente_date_field' ? [['type' => 'cliente']] : $destinatari,
            'recipients_cc' => $data['channel'] === 'whatsapp' ? [] : collect($data['recipients_cc'] ?? [])
                ->map(fn ($r) => ['type' => 'user', 'user_id' => (int) $r['user_id']])->unique('user_id')->values()->all(),
            'message_template' => $data['message_template'],
            'is_active' => $request->has('is_active') ? (bool) $data['is_active'] : true,
            'document_category_ids' => $data['document_category_ids'] ?? [],
        ];
    }

    public static function salva(Tenant $tenant, array $dati, ?Automation $automation = null): Automation
    {
        $categorie = $dati['document_category_ids'];
        unset($dati['document_category_ids']);

        $automation ??= new Automation(['tenant_id' => $tenant->id]);
        $automation->fill($dati);
        $automation->tenant_id = $tenant->id;
        $automation->save();
        $automation->documentCategories()->sync($categorie);

        return $automation;
    }

    /** Dati per la pagina/modale: un'automazione con tutti i campi dell'editor. */
    public static function perEditor(Automation $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'trigger_type' => $a->trigger_type,
            'tenant_status_id' => $a->tenant_status_id,
            'watched_field' => $a->watched_field,
            'days_before' => (int) $a->days_before,
            'offset_days' => (int) $a->offset_days,
            'ripeti' => (bool) $a->ripeti,
            'channel' => $a->channel,
            'subject' => $a->subject,
            'recipients_to' => $a->recipients_to ?: ($a->recipient ? [['type' => $a->recipient]] : []),
            'recipients_cc' => $a->recipients_cc ?? [],
            'message_template' => $a->message_template,
            'is_active' => (bool) $a->is_active,
            'document_category_ids' => $a->documentCategories->pluck('id')->all(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }
}
