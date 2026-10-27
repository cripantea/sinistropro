<?php

namespace App\Http\Controllers;

use App\Jobs\TriggerWhatsappHistorySyncJob;
use App\Models\WhatsappHistorySync;
use App\Models\WhatsappSession;
use App\Services\FusionWaConnectionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappEmbeddedSignupController extends Controller
{
    public function __construct(private readonly FusionWaConnectionClient $fusionWa) {}

    /**
     * Persiste una connessione stabilita tramite il widget Embedded Signup di
     * FusionWA. waba_id/phone_number_id/phone_number arrivano dal browser (il
     * frontend li legge dal polling di FusionWA) ma non ci si può fidare:
     * qualunque tenant-admin autenticato potrebbe mandare qui i dati di un
     * altro cliente. Prima di salvare si verifica server-to-server con
     * FusionWA (stesso principio già applicato in fusion-crm) che quella
     * connessione esista davvero, sia operativa, e corrisponda esattamente a
     * quanto dichiarato — i valori salvati sono quelli verificati da FusionWA,
     * non quelli ricevuti dal client. Nessuno scambio OAuth in questo
     * controller: il token WABA resta custodito da FusionWA, mai qui.
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

        $status = $this->fusionWa->status($authed->tenant_id);
        if ($status === null) {
            return response()->json([
                'success' => false,
                'error' => 'Verifica della connessione non disponibile al momento. Riprova tra poco; il collegamento non è stato salvato.',
            ], 503);
        }
        if (! $this->fusionWa->matches($status, $data)) {
            return response()->json([
                'success' => false,
                'error' => 'La connessione dichiarata non corrisponde a quella attiva su FusionWA per questo account.',
            ], 422);
        }
        $connection = $status['connection'];

        $giaUsato = WhatsappSession::acrossAllTenants()->where('tenant_id', '!=', $authed->tenant_id)
            ->where(fn ($q) => $q->where('phone_number_id', $connection['phoneNumberId'])->orWhere('waba_id', $connection['wabaId']))
            ->exists();
        abort_if($giaUsato, 409, 'Questo numero WhatsApp è già collegato a un altro account.');

        $session = WhatsappSession::updateOrCreate(
            ['tenant_id' => $authed->tenant_id],
            [
                'phone_number_id' => $connection['phoneNumberId'],
                'display_phone_number' => $connection['phoneNumber'],
                'waba_id' => $connection['wabaId'],
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
