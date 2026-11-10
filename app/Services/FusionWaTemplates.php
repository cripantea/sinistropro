<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Template WhatsApp via FusionWA (che custodisce il token del numero): elenco, crea, modifica, elimina. */
class FusionWaTemplates
{
    public function elenco(int $tenantId): array
    {
        $data = $this->esito($this->http()->get($this->url(), ['externalCustomerId' => (string) $tenantId]));

        return is_array($data['templates'] ?? null) ? $data['templates'] : [];
    }

    public function crea(int $tenantId, array $template): array
    {
        return $this->esito($this->http()->post($this->url(), ['externalCustomerId' => (string) $tenantId, ...$template]))['template'] ?? [];
    }

    public function modifica(int $tenantId, array $template): array
    {
        return $this->esito($this->http()->put($this->url(), ['externalCustomerId' => (string) $tenantId, ...$template]))['template'] ?? [];
    }

    public function elimina(int $tenantId, string $name, ?string $externalId): void
    {
        $this->esito($this->http()->delete($this->url().'?'.http_build_query(array_filter([
            'externalCustomerId' => (string) $tenantId, 'name' => $name, 'externalId' => $externalId,
        ]))));
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'x-fusionwa-api-key' => config('services.fusionwa.api_key'),
            'x-fusionwa-api-secret' => config('services.fusionwa.api_secret'),
        ])->acceptJson()->timeout(20);
    }

    private function url(): string
    {
        return rtrim((string) config('services.fusionwa.base_url'), '/').'/api/v1/templates';
    }

    /** Risposta valida o eccezione col messaggio di Meta/FusionWA (già leggibile). */
    private function esito($response): array
    {
        $data = $response->json() ?? [];
        if ($response->successful() && ($data['status'] ?? null) === 'success') {
            return $data;
        }
        $motivo = $data['details']['error_user_msg'] ?? $data['details']['message'] ?? $data['error'] ?? ('errore '.$response->status());

        throw new RuntimeException(is_string($motivo) ? $motivo : json_encode($motivo));
    }
}
