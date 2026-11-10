<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;

/**
 * Chi può usare le Impostazioni dell'agenzia: l'amministratore dell'agenzia, il superadmin
 * dentro l'agenzia, e il superadmin in assistenza "come" un utente qualsiasi.
 */
class AccessoImpostazioni
{
    public static function consentito(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->isTenantAdmin() || $user->role === 'superadmin') {
            return true;
        }
        $impersonatore = session('impersonator_id');

        return $impersonatore && User::whereKey($impersonatore)->where('role', 'superadmin')->exists();
    }

    /** Agenzia su cui si lavora (403 se non consentito, 404 se il superadmin non è dentro un'agenzia). */
    public static function tenant(): Tenant
    {
        abort_unless(self::consentito(), 403, 'Solo l\'amministratore dell\'agenzia può cambiare le impostazioni.');
        $tenant = TenantContext::tenant();
        abort_unless($tenant, 404);

        return $tenant;
    }
}
