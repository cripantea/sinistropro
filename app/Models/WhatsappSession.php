<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappSession extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'tenant_id',
        'phone_number_id',
        'display_phone_number',
        'status',
        'last_connected_at',
        'last_event_at',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
        'last_event_at'     => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
