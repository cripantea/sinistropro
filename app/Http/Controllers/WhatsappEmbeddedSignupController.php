<?php

namespace App\Http\Controllers;

use App\Jobs\TriggerWhatsappHistorySyncJob;
use App\Models\WhatsappHistorySync;
use App\Models\WhatsappSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappEmbeddedSignupController extends Controller
{
    /**
     * Persiste una connessione già stabilita tramite il widget Embedded Signup
     * di FusionWA: il frontend ha già verificato lo stato CONNECTED
     * interrogando FusionWA direttamente (vedi EmbeddedSignupButton.vue), qui
     * ci limitiamo a registrarlo. Nessuno scambio OAuth in questo controller:
     * il token WABA resta custodito da FusionWA, mai qui.
     */
    public function sync(Request $request): JsonResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403, 'Solo il tenant-admin può collegare un numero WhatsApp.');

        $data = $request->validate([
            'waba_id' => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
            'phone_number' => ['required', 'string'],
        ]);

        $session = WhatsappSession::updateOrCreate(
            ['tenant_id' => $authed->tenant_id],
            [
                'phone_number_id' => $data['phone_number_id'],
                'display_phone_number' => $data['phone_number'],
                'waba_id' => $data['waba_id'],
                'access_token' => null,
                'is_on_biz_app' => true,
                'platform_type' => 'CLOUD_API',
                'status' => 'active',
                'connected_by_user_id' => $authed->id,
                'history_sync_status' => 'pending',
                'disconnection_reason' => null,
                'disconnected_at' => null,
                'last_connected_at' => now(),
            ]
        );

        foreach ([['sync_type' => 'smb_app_state_sync', 'phase' => null], ['sync_type' => 'history', 'phase' => 0]] as $sync) {
            WhatsappHistorySync::updateOrCreate(
                [
                    'whatsapp_session_id' => $session->id,
                    'sync_type' => $sync['sync_type'],
                    'phase' => $sync['phase'],
                ],
                [
                    'tenant_id' => $authed->tenant_id,
                    'status' => 'requested',
                    'requested_at' => now(),
                ]
            );
        }

        TriggerWhatsappHistorySyncJob::dispatch($session->id)->delay(now()->addSeconds(30));

        return response()->json([
            'success' => true,
            'session' => [
                'status' => $session->status,
                'phoneNumber' => $session->display_phone_number,
            ],
        ]);
    }
}
