<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    use BelongsToTenant;

    protected $table = 'email_attachments';

    protected $fillable = [
        'tenant_id',
        'email_message_id',
        'filename',
        'mime_type',
        'size',
        's3_key',
        'content_id',
        'is_inline',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
    ];

    /** Si possono aprire nella pagina (anteprima); il resto solo download. */
    public const ANTEPRIMA = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    public function tipoAnteprima(): ?string
    {
        $mime = strtolower((string) $this->mime_type);
        if (! in_array($mime, self::ANTEPRIMA, true)) {
            return null;
        }

        return $mime === 'application/pdf' ? 'pdf' : 'image';
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'inline' => (bool) $this->is_inline,
            'preview' => $this->tipoAnteprima(),
            'viewUrl' => $this->tipoAnteprima() ? route('email.attachments.view', $this, false) : null,
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
