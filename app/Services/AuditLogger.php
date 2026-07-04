<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Registra un'azione nel log di audit.
     * Ritorna null silenziosamente se non c'è un utente autenticato
     * (es: job in coda, comandi Artisan) — le azioni di sistema non vengono loggate.
     */
    public function log(
        string $action,
        Model  $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ?AuditLog {
        if (! auth()->check()) {
            return null;
        }

        $request = request();

        return AuditLog::create([
            'tenant_id'               => auth()->user()->tenant_id,
            'user_id'                 => auth()->id(),
            'impersonated_by_user_id' => session('impersonator_id'),
            'action'                  => $action,
            'auditable_type'          => $auditable->getMorphClass(),
            'auditable_id'            => $auditable->getKey(),
            'old_values'              => $oldValues ?: null,
            'new_values'              => $newValues ?: null,
            'ip_address'              => $request->ip(),
            'user_agent'              => $request->userAgent(),
        ]);
    }
}
