<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        // Popola tenant_id automaticamente al momento della creazione.
        static::creating(function (self $model): void {
            if (auth()->check() && empty($model->tenant_id)) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /**
     * Query esplicita per un tenant specifico (usare nei job/comandi artisan).
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable() . '.tenant_id', $tenantId);
    }

    /**
     * Bypassa il filtro tenant (solo per admin globali o operazioni di sistema).
     */
    public function scopeAcrossAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
