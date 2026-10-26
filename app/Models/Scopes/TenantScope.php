<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            // Senza tenant si vede tutto solo il superadmin (o il codice di sistema senza utente):
            // qualsiasi altro utente autenticato non vede nulla, mai i dati di tutti i tenant.
            if (auth()->check() && auth()->user()->role !== 'superadmin') {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $builder->where($model->getTable() . '.tenant_id', $tenantId);
    }
}
