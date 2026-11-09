<?php

namespace App\Models;

use App\Services\MicrosoftMail;
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
        'auth_type',
        'oauth_refresh_token',
        'oauth_access_token',
        'oauth_expires_at',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'oauth_refresh_token' => 'encrypted',
        'oauth_access_token' => 'encrypted',
        'oauth_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'port' => 'integer',
    ];

    protected $hidden = [
        'password',
        'oauth_refresh_token',
        'oauth_access_token',
    ];

    public function isMicrosoft(): bool
    {
        return $this->auth_type === 'microsoft';
    }

    /** Password o, per Microsoft, token di accesso valido (rinnovato se serve). */
    public function secret(): ?string
    {
        return $this->isMicrosoft() ? app(MicrosoftMail::class)->accessToken($this) : $this->password;
    }

    /** Configurazione per Webklex\PHPIMAP\ClientManager::make(). */
    public function imapClientConfig(): array
    {
        return [
            'host' => $this->imap_host,
            'port' => $this->imap_port ?: 993,
            'encryption' => $this->imap_encryption ?: 'ssl',
            'validate_cert' => true,
            'username' => $this->username,
            'password' => $this->secret(),
            'authentication' => $this->isMicrosoft() ? 'oauth' : null,
        ];
    }

    /** @return array{host: ?string, port: ?int, encryption: ?string, username: ?string, password: ?string, oauth: bool} */
    public function smtpConfig(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'password' => $this->secret(),
            'oauth' => $this->isMicrosoft(),
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
