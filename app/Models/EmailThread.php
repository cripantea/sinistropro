<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailThread extends Model
{
    use BelongsToTenant;

    protected $table = 'email_threads';

    protected $fillable = [
        'tenant_id',
        'counterpart_email',
        'counterpart_name',
        'subject',
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

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class)
            ->orderBy('email_timestamp')
            ->orderBy('created_at');
    }
}
