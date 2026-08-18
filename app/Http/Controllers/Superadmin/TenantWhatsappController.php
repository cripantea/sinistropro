<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class TenantWhatsappController extends Controller
{
    public function disconnect(Tenant $tenant): RedirectResponse
    {
        $session = $tenant->whatsappSession;

        if ($session) {
            // Non elimina la riga (conserva conversazioni/messaggi collegati): azzera
            // solo le credenziali per forzare un nuovo collegamento Embedded Signup.
            $session->update([
                'status' => 'disabled',
                'access_token' => null,
                'waba_id' => null,
                'is_on_biz_app' => false,
                'disconnection_reason' => 'disconnesso manualmente da supporto',
                'disconnected_at' => now(),
            ]);
        }

        return redirect()
            ->to(route('superadmin.tenants.edit', $tenant).'?tab=whatsapp')
            ->with('success', 'Numero WhatsApp disconnesso.');
    }
}
