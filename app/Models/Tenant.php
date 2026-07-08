<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function statuses(): HasMany
    {
        return $this->hasMany(TenantStatus::class)->orderBy('order');
    }

    public function pratiche(): HasMany
    {
        return $this->hasMany(Pratica::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function moduleTemplates(): HasMany
    {
        return $this->hasMany(ModuleTemplate::class)->orderBy('name');
    }

    public function whatsappSession(): HasOne
    {
        return $this->hasOne(WhatsappSession::class);
    }

    public function documentCategories(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCategory::class, 'tenant_document_categories')
            ->withPivot('max_file_size_mb', 'is_enabled')
            ->withTimestamps();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getDefaultNoticeDays(): int
    {
        return (int) $this->getSetting('default_notice_days', 30);
    }

    /** @return array<int, array{name: string, label: string, type: string}> */
    public function getCustomFieldsSchema(): array
    {
        return $this->getSetting('custom_fields_schema', []);
    }
}
