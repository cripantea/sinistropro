<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Centralizza la risoluzione del tenant corrente.
 *
 * Per utenti normali: restituisce tenant_id dall'utente autenticato.
 * Per superadmin: restituisce il tenant selezionato in sessione (sa_tenant_id),
 * permettendo di navigare i dati di qualsiasi tenant senza impersonazione.
 */
class TenantContext
{
    public static function id(): ?int
    {
        if (! auth()->check()) {
            return null;
        }

        $user = auth()->user();

        if ($user->role === 'superadmin') {
            return session('sa_tenant_id');
        }

        return $user->tenant_id;
    }

    public static function tenant(): ?Tenant
    {
        $id = static::id();
        return $id ? Tenant::find($id) : null;
    }

    public static function set(int $tenantId): void
    {
        session(['sa_tenant_id' => $tenantId]);
    }

    public static function clear(): void
    {
        session()->forget('sa_tenant_id');
    }
}
