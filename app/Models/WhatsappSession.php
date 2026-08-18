<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappSession extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'tenant_id',
        'phone_number_id',
        'waba_id',
        'business_id',
        'access_token',
        'is_on_biz_app',
        'platform_type',
        'display_phone_number',
        'status',
        'connected_by_user_id',
        'history_sync_status',
        'disconnection_reason',
        'disconnected_at',
        'last_connected_at',
        'last_event_at',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'is_on_biz_app' => 'boolean',
        'disconnected_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function historySyncs(): HasMany
    {
        return $this->hasMany(WhatsappHistorySync::class);
    }
}
