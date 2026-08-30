<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'email_messages';

    protected $fillable = [
        'tenant_id',
        'email_thread_id',
        'user_id',
        'direction',
        'folder',
        'from_address',
        'from_name',
        'to_addresses',
        'cc_addresses',
        'subject',
        'body_html',
        'body_text',
        'message_id',
        'in_reply_to',
        'status',
        'email_timestamp',
    ];

    protected $casts = [
        'to_addresses' => 'array',
        'cc_addresses' => 'array',
        'email_timestamp' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'email_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }
}
