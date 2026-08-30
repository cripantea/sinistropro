<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMailSettings extends Model
{
    protected $table = 'tenant_mail_settings';

    protected $fillable = [
        'tenant_id',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'is_active',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_last_uid_inbox',
        'imap_last_uid_sent',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean',
        'port' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
