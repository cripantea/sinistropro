<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Automation extends Model
{
    use BelongsToTenant;

    protected $table = 'automations';

    protected $fillable = [
        'tenant_id',
        'name',
        'trigger_type',
        'tenant_status_id',
        'watched_field',
        'days_before',
        'offset_days',
        'ripeti',
        'channel',
        'subject',
        'recipient',
        'recipients_to',
        'recipients_cc',
        'message_template',
        'is_active',
        'requires_confirmation',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'requires_confirmation' => 'boolean',
        'days_before'           => 'integer',
        'offset_days'           => 'integer',
        'ripeti'                => 'boolean',
        'recipients_to'         => 'array',
        'recipients_cc'         => 'array',
    ];

    /** Inneschi supportati, con l'etichetta mostrata all'agenzia. */
    public const TRIGGER = [
        'status'             => 'Cambio di stato del sinistro',
        'pratica_created'    => 'Nuovo sinistro aperto',
        'date_field'         => 'Cambio di una data del sinistro',
        'date_reminder'      => 'Giorni prima/dopo una data del sinistro',
        'status_duration'    => 'Sinistro fermo da X giorni in uno stato (sollecito)',
        'cliente_date_field' => 'Scadenza di una data del cliente',
    ];

    /** Automazioni "a stato" che scattano entrando in questo stato (vuoto = qualsiasi stato). */
    public function scopePerStato($query, int $statusId)
    {
        return $query->where('trigger_type', 'status')
            ->where(fn ($q) => $q->where('tenant_status_id', $statusId)->orWhereNull('tenant_status_id'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TenantStatus::class, 'tenant_status_id');
    }

    public function documentCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentCategory::class,
            'automation_document_categories',
            'automation_id',
            'document_category_id'
        );
    }
}
