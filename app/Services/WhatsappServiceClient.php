<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappServiceClient
{
    private string $baseUrl;
    private ?string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.whatsapp.url'), '/');
        $this->secret = config('services.whatsapp.secret');
    }

    public function startSession(int $tenantId): array
    {
        return $this->request('post', "/sessions/{$tenantId}/start");
    }

    public function status(int $tenantId): array
    {
        return $this->request('get', "/sessions/{$tenantId}/status");
    }

    public function sendMessage(int $tenantId, string $to, string $message): array
    {
        return $this->request('post', "/sessions/{$tenantId}/messages", [
            'to' => $to,
            'message' => $message,
        ]);
    }

    public function stopSession(int $tenantId): array
    {
        return $this->request('post', "/sessions/{$tenantId}/stop");
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        /** @var Response $response */
        $response = Http::withHeaders(['X-Internal-Secret' => $this->secret])
            ->timeout(15)
            ->{$method}("{$this->baseUrl}{$path}", $payload);

        if ($response->failed()) {
            Log::error('WhatsappServiceClient: richiesta fallita', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json() ?? [];
    }
}
