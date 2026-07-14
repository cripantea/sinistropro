<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TenantStatus extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'color', 'order', 'is_closed', 'is_initial', 'responsible_role', 'send_email_notification'];

    protected $casts = [
        'is_closed'                => 'boolean',
        'is_initial'               => 'boolean',
        'send_email_notification'  => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class, 'current_status_id');
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class, 'tenant_status_id');
    }
}
