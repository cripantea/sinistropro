<?php

namespace App\Services;

use App\Models\WhatsappSession;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappCloudApiClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://graph.facebook.com/'.config('services.whatsapp_cloud.api_version');
    }

    public function sendText(WhatsappSession $session, string $to, string $body): array
    {
        return $this->request($session, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    public function sendTemplate(WhatsappSession $session, string $to, string $name, string $languageCode, array $components = []): array
    {
        return $this->request($session, [
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

    private function request(WhatsappSession $session, array $payload): array
    {
        // Fallback al token globale per i tenant collegati manualmente
        // (comando whatsapp:link-number), che non hanno un access_token per-WABA.
        $token = $session->access_token ?: config('services.whatsapp_cloud.token');

        /** @var Response $response */
        $response = Http::withToken($token)
            ->timeout(15)
            ->post("{$this->baseUrl}/{$session->phone_number_id}/messages", $payload);

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
