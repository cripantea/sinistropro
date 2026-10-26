<?php

namespace App\Http\Controllers;

use App\Jobs\TriggerWhatsappHistorySyncJob;
use App\Models\WhatsappHistorySync;
use App\Models\WhatsappSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsappEmbeddedSignupController extends Controller
{
    /**
     * Persiste una connessione già stabilita tramite il widget Embedded Signup
     * di FusionWA. Gli identificativi (waba/phone number id) NON si prendono dal
     * browser: si rileggono qui da FusionWA per il tenant dell'utente, perché il
     * webhook assegna i messaggi al tenant proprio in base a questi id.
     * Nessuno scambio OAuth in questo controller: il token WABA resta custodito
     * da FusionWA, mai qui.
     */
    public function sync(Request $request): JsonResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403, 'Solo il tenant-admin può collegare un numero WhatsApp.');

        $data = $this->connectionFromFusionWa($authed->tenant_id);
        abort_unless($data, 422, 'Il collegamento WhatsApp non risulta completato su FusionWA. Riprova tra qualche secondo.');

        $giaUsato = WhatsappSession::acrossAllTenants()->where('tenant_id', '!=', $authed->tenant_id)
            ->where(fn ($q) => $q->where('phone_number_id', $data['phone_number_id'])->orWhere('waba_id', $data['waba_id']))
            ->exists();
        abort_if($giaUsato, 409, 'Questo numero WhatsApp è già collegato a un altro account.');

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

    /**
     * Stato del collegamento letto da FusionWA (stesso endpoint che interroga il widget),
     * per il solo tenant corrente. Null se non è CONNECTED o mancano gli id.
     *
     * @return array{waba_id: string, phone_number_id: string, phone_number: string}|null
     */
    private function connectionFromFusionWa(int $tenantId): ?array
    {
        $baseUrl = rtrim((string) config('services.fusionwa.base_url'), '/');

        $response = Http::withHeaders([
            'x-fusionwa-api-key' => config('services.fusionwa.api_key'),
            'x-fusionwa-api-secret' => config('services.fusionwa.api_secret'),
        ])
            ->timeout(15)
            ->get("{$baseUrl}/api/v1/widget/status", ['customerId' => (string) $tenantId]);

        $status = $response->successful() ? ($response->json() ?? []) : [];

        if (($status['status'] ?? null) !== 'CONNECTED'
            || blank($status['wabaId'] ?? null)
            || blank($status['phoneNumberId'] ?? null)
            || blank($status['phoneNumber'] ?? null)) {
            return null;
        }

        return [
            'waba_id' => (string) $status['wabaId'],
            'phone_number_id' => (string) $status['phoneNumberId'],
            'phone_number' => (string) $status['phoneNumber'],
        ];
    }
}
