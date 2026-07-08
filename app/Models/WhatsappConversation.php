<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappConversation extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'tenant_id',
        'pratica_id',
        'phone_number',
        'contact_name',
        'last_message_at',
        'last_message_preview',
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class)->orderBy('created_at');
    }
}
