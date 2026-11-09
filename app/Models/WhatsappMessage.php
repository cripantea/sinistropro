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
        'media_filename',
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

    /** Tipi di allegato che si possono aprire dalla chat (scaricati via FusionWA). */
    public const MEDIA_APRIBILI = ['image', 'sticker', 'video', 'audio', 'document'];

    /** Extract only metadata; never accept a remote download URL from a webhook. */
    public static function mediaAttributes(array $message): array
    {
        $type = $message['type'] ?? null;
        $media = is_string($type) && is_array($message[$type] ?? null) ? $message[$type] : [];
        $id = $media['id'] ?? null;
        $filename = is_string($media['filename'] ?? null) ? trim(basename(str_replace('\\', '/', $media['filename']))) : '';
        return [
            'media_id' => is_string($id) && preg_match('/^\d{1,64}$/D', $id) ? $id : null,
            'media_mime_type' => is_string($media['mime_type'] ?? null) ? substr($media['mime_type'], 0, 100) : null,
            'media_filename' => $filename !== '' ? mb_substr($filename, 0, 200) : null,
        ];
    }

    /** Testo del messaggio, o didascalia di foto, video e documenti. */
    public static function bodyFrom(array $message): ?string
    {
        return $message['text']['body']
            ?? $message['image']['caption']
            ?? $message['video']['caption']
            ?? $message['document']['caption']
            ?? null;
    }

    /** Anteprima nella lista chat per i messaggi senza testo. */
    public static function etichettaMedia(?string $type): string
    {
        return match ($type) {
            null => '',
            'image' => '📷 Foto',
            'video' => '🎥 Video',
            'audio' => '🎤 Messaggio vocale',
            'document' => '📄 Documento',
            'sticker' => 'Sticker',
            'location' => '📍 Posizione',
            'contacts' => '👤 Contatto',
            default => '[media]',
        };
    }

    public function mediaUrl(): ?string
    {
        return in_array($this->media_type, self::MEDIA_APRIBILI, true) && $this->media_id
            ? route('whatsapp.media', ['message' => $this->id], false) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
