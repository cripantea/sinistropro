<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappCloudApiClient
{
    private string $baseUrl;
    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = 'https://graph.facebook.com/' . config('services.whatsapp_cloud.api_version');
        $this->token = config('services.whatsapp_cloud.token');
    }

    public function sendText(string $phoneNumberId, string $to, string $body): array
    {
        return $this->request($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $body],
        ]);
    }

    public function sendTemplate(string $phoneNumberId, string $to, string $name, string $languageCode, array $components = []): array
    {
        return $this->request($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'       => $name,
                'language'   => ['code' => $languageCode],
                'components' => $components,
            ],
        ]);
    }

    private function request(string $phoneNumberId, array $payload): array
    {
        /** @var Response $response */
        $response = Http::withToken($this->token)
            ->timeout(15)
            ->post("{$this->baseUrl}/{$phoneNumberId}/messages", $payload);

        if ($response->failed()) {
            Log::error('WhatsappCloudApiClient: richiesta fallita', [
                'phoneNumberId' => $phoneNumberId,
                'status'        => $response->status(),
                'body'          => $response->body(),
            ]);
        }

        return $response->json() ?? [];
    }
}
