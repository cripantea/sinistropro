<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Pratica extends Model
{
    use HasFactory, BelongsToTenant, HasAuditLog;

    protected $table = 'pratiche';

    protected $fillable = [
        'tenant_id',
        'utente_creatore_id',
        'cliente_id',
        'current_status_id',
        'data_prossimo_avviso',
        'custom_fields',
    ];

    protected $casts = [
        'data_prossimo_avviso' => 'date',
        'custom_fields'        => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function utenteCreatore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utente_creatore_id');
    }

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(TenantStatus::class, 'current_status_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function allegati(): HasMany
    {
        return $this->hasMany(Allegato::class);
    }

    public function note(): HasMany
    {
        return $this->hasMany(PraticaNota::class)->orderBy('created_at');
    }

    public function ispezioni(): HasMany
    {
        return $this->hasMany(Ispezione::class)->orderByDesc('created_at');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(PraticaModule::class)->with('template');
    }

    public function whatsappConversation(): HasOne
    {
        return $this->hasOne(WhatsappConversation::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function getCustomField(string $key, mixed $default = null): mixed
    {
        return data_get($this->custom_fields, $key, $default);
    }
}
