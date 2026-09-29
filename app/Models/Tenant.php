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

    /** Feature slug → [label, default]. Default true = già attiva per tutti i tenant esistenti. */
    const AVAILABLE_FEATURES = [
        'whatsapp'             => ['label' => 'Integrazione WhatsApp',            'default' => true],
        'moduli_pdf'           => ['label' => 'Moduli PDF dinamici',               'default' => true],
        'automazioni'          => ['label' => 'Automazioni Workflow',              'default' => true],
        'kanban'               => ['label' => 'Board Kanban',                      'default' => true],
        'clienti'              => ['label' => 'Gestione Clienti',                   'default' => false],
        'lista_personalizzate' => ['label' => 'Liste con valori personalizzati',   'default' => false],
        'import_clienti'       => ['label' => 'Import clienti (JSON/XML/CSV)',     'default' => false],
    ];

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

    public function clienti(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function fieldDictionaryEntries(): HasMany
    {
        return $this->hasMany(FieldDictionaryEntry::class)->orderBy('label');
    }

    public function mailSettings(): HasOne
    {
        return $this->hasOne(TenantMailSettings::class);
    }

    public function initialStatus(): ?TenantStatus
    {
        // Fallback al primo per ordine se nessuno stato è ancora marcato esplicitamente
        // (es. tenant creati prima dell'introduzione del flag is_initial).
        return $this->statuses()->where('is_initial', true)->first()
            ?? $this->statuses()->first();
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

    public function hasFeature(string $feature): bool
    {
        $default = self::AVAILABLE_FEATURES[$feature]['default'] ?? true;
        return (bool) $this->getSetting("features.{$feature}", $default);
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

    /** @return array<int, array{name: string, label: string, type: string}> */
    public function getClienteCustomFieldsSchema(): array
    {
        return $this->getSetting('cliente_custom_fields_schema', []);
    }
}
