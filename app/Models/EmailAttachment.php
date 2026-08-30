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
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
