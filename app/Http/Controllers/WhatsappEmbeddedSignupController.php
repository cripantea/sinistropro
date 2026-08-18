<?php

namespace App\Http\Controllers;

use App\Jobs\TriggerWhatsappHistorySyncJob;
use App\Models\WhatsappHistorySync;
use App\Models\WhatsappSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappEmbeddedSignupController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403, 'Solo il tenant-admin può collegare un numero WhatsApp.');

        $data = $request->validate([
            'code' => ['required', 'string'],
            'waba_id' => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
            'business_id' => ['nullable', 'string'],
        ]);

        $graphVersion = config('services.facebook.graph_version');
        $baseUrl = "https://graph.facebook.com/{$graphVersion}";

        $tokenResponse = Http::get("{$baseUrl}/oauth/access_token", [
            'client_id' => config('services.facebook.app_id'),
            'client_secret' => config('services.facebook.app_secret'),
            'code' => $data['code'],
        ]);

        if ($tokenResponse->failed() || ! $tokenResponse->json('access_token')) {
            Log::error('WhatsappEmbeddedSignupController: scambio code->token fallito', [
                'tenant_id' => $authed->tenant_id,
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);

            return response()->json([
                'message' => 'Impossibile completare il collegamento con Meta. Riprova.',
            ], 422);
        }

        $accessToken = $tokenResponse->json('access_token');

        $phoneCheck = Http::withToken($accessToken)
            ->get("{$baseUrl}/{$data['phone_number_id']}", [
                'fields' => 'is_on_biz_app,platform_type,display_phone_number',
            ]);

        $isOnBizApp = $phoneCheck->json('is_on_biz_app');
        $platformType = $phoneCheck->json('platform_type');
        $displayPhoneNumber = $phoneCheck->json('display_phone_number');

        if ($phoneCheck->failed() || $isOnBizApp !== true || $platformType !== 'CLOUD_API') {
            Log::warning('WhatsappEmbeddedSignupController: numero non idoneo alla coexistence', [
                'tenant_id' => $authed->tenant_id,
                'phone_number_id' => $data['phone_number_id'],
                'is_on_biz_app' => $isOnBizApp,
                'platform_type' => $platformType,
            ]);

            return response()->json([
                'message' => 'Questo numero non risulta idoneo alla coesistenza (verifica che sia attivo su WhatsApp Business App e non sia un Account Ufficiale/badge blu).',
            ], 422);
        }

        // Passo obbligatorio: senza questa iscrizione l'app non riceve alcun webhook per questa WABA.
        Http::withToken($accessToken)->post("{$baseUrl}/{$data['waba_id']}/subscribed_apps");

        $session = WhatsappSession::updateOrCreate(
            ['tenant_id' => $authed->tenant_id],
            [
                'phone_number_id' => $data['phone_number_id'],
                'display_phone_number' => $displayPhoneNumber,
                'waba_id' => $data['waba_id'],
                'business_id' => $data['business_id'] ?? null,
                'access_token' => $accessToken,
                'is_on_biz_app' => true,
                'platform_type' => $platformType,
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
