<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'tenant_id',
        'whatsapp_conversation_id',
        'user_id',
        'direction',
        'source',
        'body',
        'media_type',
        'media_id',
        'media_url',
        'media_mime_type',
        'wa_message_id',
        'wa_timestamp',
        'status',
    ];

    protected $casts = [
        'wa_timestamp' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }

    /** Extract only metadata; never accept a remote download URL from a webhook. */
    public static function mediaAttributes(array $message): array
    {
        $type = $message['type'] ?? null;
        $media = is_string($type) && is_array($message[$type] ?? null) ? $message[$type] : [];
        $id = $media['id'] ?? null;
        return [
            'media_id' => is_string($id) && preg_match('/^\d{1,64}$/D', $id) ? $id : null,
            'media_mime_type' => is_string($media['mime_type'] ?? null) ? substr($media['mime_type'], 0, 100) : null,
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->media_type === 'image' && $this->media_id
            ? route('whatsapp.media', ['message' => $this->id], false) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
