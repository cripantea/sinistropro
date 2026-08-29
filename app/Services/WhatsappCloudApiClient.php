<?php

namespace App\Services;

use App\Models\WhatsappSession;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappCloudApiClient
{
    private string $graphBaseUrl;

    private string $fusionwaBaseUrl;

    public function __construct()
    {
        $this->graphBaseUrl = 'https://graph.facebook.com/'.config('services.whatsapp_cloud.api_version');
        $this->fusionwaBaseUrl = rtrim((string) config('services.fusionwa.base_url'), '/');
    }

    public function sendText(WhatsappSession $session, string $to, string $body): array
    {
        if ($this->isFusionWaManaged($session)) {
            return $this->requestViaFusionWa($session, $to, ['message' => $body]);
        }

        return $this->requestViaGraph($session, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    public function sendTemplate(WhatsappSession $session, string $to, string $name, string $languageCode, array $components = []): array
    {
        if ($this->isFusionWaManaged($session)) {
            $bodyParams = collect($components)->firstWhere('type', 'body')['parameters'] ?? [];
            $bodyParams = collect($bodyParams)->pluck('text')->values()->all();

            return $this->requestViaFusionWa($session, $to, [
                'template' => array_filter([
                    'name' => $name,
                    'language' => $languageCode,
                    'bodyParams' => $bodyParams,
                ], fn ($v) => $v !== [] && $v !== null),
            ]);
        }

        return $this->requestViaGraph($session, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $name,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Le sessioni collegate tramite Embedded Signup + FusionWA hanno sempre un
     * waba_id; quelle create col comando whatsapp:link-number (token condiviso
     * globale, nessuna coexistence) no — per queste resta l'invio diretto a Graph.
     */
    private function isFusionWaManaged(WhatsappSession $session): bool
    {
        return filled($session->waba_id);
    }

    private function requestViaFusionWa(WhatsappSession $session, string $to, array $extra): array
    {
        /** @var Response $response */
        $response = Http::withHeaders([
            'x-fusionwa-api-key' => config('services.fusionwa.api_key'),
            'x-fusionwa-api-secret' => config('services.fusionwa.api_secret'),
        ])
            ->timeout(15)
            ->post("{$this->fusionwaBaseUrl}/api/v1/messages/send", array_merge([
                'externalCustomerId' => (string) $session->tenant_id,
                'toPhoneNumber' => $to,
            ], $extra));

        if ($response->failed()) {
            Log::error('WhatsappCloudApiClient: richiesta FusionWA fallita', [
                'tenantId' => $session->tenant_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        $data = $response->json() ?? [];

        // Stessa forma di risposta di Graph API attesa dai chiamanti esistenti
        // (es. WhatsappConversationController legge $result['messages'][0]['id']).
        return [
            'messages' => [['id' => $data['messageId'] ?? null]],
        ];
    }

    private function requestViaGraph(WhatsappSession $session, array $payload): array
    {
        // Fallback al token globale per i tenant collegati manualmente
        // (comando whatsapp:link-number), che non hanno un access_token per-WABA.
        $token = $session->access_token ?: config('services.whatsapp_cloud.token');

        /** @var Response $response */
        $response = Http::withToken($token)
            ->timeout(15)
            ->post("{$this->graphBaseUrl}/{$session->phone_number_id}/messages", $payload);

        if ($response->status() === 429) {
            Log::warning('WhatsappCloudApiClient: throughput superato (429)', [
                'phoneNumberId' => $session->phone_number_id,
                'body' => $response->body(),
            ]);
        } elseif ($response->failed()) {
            Log::error('WhatsappCloudApiClient: richiesta fallita', [
                'phoneNumberId' => $session->phone_number_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->json() ?? [];
    }
}
