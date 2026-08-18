<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappHistorySync extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_history_syncs';

    protected $fillable = [
        'tenant_id',
        'whatsapp_session_id',
        'sync_type',
        'phase',
        'status',
        'progress',
        'last_chunk_order',
        'error_code',
        'error_message',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class, 'whatsapp_session_id');
    }
}
