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
        'import_clienti'       => ['label' => 'Import clienti (Excel/CSV/JSON/XML)', 'default' => false],
        'veicoli'              => ['label' => 'Veicoli: sezione veicolo sempre visibile nel nuovo sinistro', 'default' => false],
    ];

    protected $casts = [
        'settings'                          => 'array',
        'billing_details'                   => 'array',
        'free_until'                        => 'date',
        'setup_fee_paid_at'                 => 'datetime',
        'subscription_ends_at'              => 'datetime',
        'subscription_cancel_at_period_end' => 'boolean',
    ];

    /** Stati Stripe con cui l'abbonamento dà accesso pieno (past_due: Stripe sta ancora riprovando l'addebito). */
    const SUBSCRIPTION_ACTIVE_STATUSES = ['active', 'trialing', 'past_due'];

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

    // ── Abbonamento ─────────────────────────────────────────────────────────

    public function isBilledExternally(): bool
    {
        return $this->billing_mode !== 'stripe';
    }

    /**
     * Accesso pieno (creare e modificare, automazioni e avvisi attivi). Senza accesso il tenant
     * resta in sola lettura: vede tutto ma non scrive, e non parte nessun messaggio automatico.
     */
    public function hasAccess(): bool
    {
        if ($this->isBilledExternally()) {
            return true;
        }

        if ($this->free_until && today()->lte($this->free_until)) {
            return true;
        }

        return in_array($this->subscription_status, self::SUBSCRIPTION_ACTIVE_STATUSES, true);
    }

    /**
     * Prezzo in centesimi (IVA inclusa) per 'month' o 'year': quello del tenant o lo standard.
     * Con solo il mensile personalizzato, l'annuale vale 10 mesi (come lo standard 99 → 990).
     */
    public function priceCents(string $interval): int
    {
        if ($interval !== 'year') {
            return $this->price_monthly_cents ?? (int) config('billing.price_monthly_cents');
        }

        return $this->price_yearly_cents
            ?? ($this->price_monthly_cents ? $this->price_monthly_cents * 10 : (int) config('billing.price_yearly_cents'));
    }

    /** Costo di attivazione ancora da pagare (una tantum, con la prima rata). */
    public function pendingSetupFeeCents(): int
    {
        return $this->setup_fee_paid_at ? 0 : (int) $this->setup_fee_cents;
    }

    public function hasSubscription(): bool
    {
        return $this->stripe_subscription_id !== null
            && ! in_array($this->subscription_status, ['canceled', 'incomplete_expired'], true);
    }

    /**
     * Stato sintetico per pagine e badge: esterno | gratis | attivo | disdetto | in_ritardo | non_attivo.
     */
    public function billingState(): string
    {
        return match (true) {
            $this->isBilledExternally() => 'esterno',
            in_array($this->subscription_status, ['active', 'trialing'], true) => $this->subscription_cancel_at_period_end ? 'disdetto' : 'attivo',
            $this->subscription_status === 'past_due' => 'in_ritardo',
            $this->free_until && today()->lte($this->free_until) => 'gratis',
            default => 'non_attivo',
        };
    }

    /** Riepilogo per le pagine (cliente e superadmin): niente id Stripe. */
    public function billingSummary(): array
    {
        return [
            'state'                => $this->billingState(),
            'has_access'           => $this->hasAccess(),
            'has_subscription'     => $this->hasSubscription(),
            'interval'             => $this->subscription_interval,
            'ends_at'              => $this->subscription_ends_at?->toDateString(),
            'cancel_at_period_end' => $this->subscription_cancel_at_period_end,
            'free_until'           => $this->free_until?->toDateString(),
            'price_monthly_cents'  => $this->priceCents('month'),
            'price_yearly_cents'   => $this->priceCents('year'),
            'setup_fee_cents'      => $this->pendingSetupFeeCents(),
        ];
    }
}
