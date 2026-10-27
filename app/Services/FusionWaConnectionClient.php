<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FusionWaConnectionClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.fusionwa.base_url'), '/');
    }

    /**
     * Legge lo stato reale della connessione per questo customer da FusionWA
     * (server-to-server, con l'API secret — mai esposta al browser). Va
     * chiamato prima di salvare qualunque dato di collegamento (waba_id,
     * phone_number_id, phone_number) che arriva dal frontend: quei valori li
     * manda il browser, e senza questa verifica chiunque sia autenticato
     * come tenant-admin potrebbe dichiarare il numero di un altro cliente
     * (vedi WhatsappEmbeddedSignupController::sync).
     *
     * Ritorna null se la richiesta fallisce (rete, FusionWA non raggiungibile,
     * risposta malformata): un fallimento non deve mai essere trattato come
     * "nessuna connessione", altrimenti un guasto temporaneo si confonderebbe
     * con un vero stato "non connesso".
     */
    public function status(string|int $externalCustomerId): ?array
    {
        $response = Http::withHeaders([
            'x-fusionwa-api-key' => config('services.fusionwa.api_key'),
            'x-fusionwa-api-secret' => config('services.fusionwa.api_secret'),
        ])
            ->timeout(10)
            ->get("{$this->baseUrl}/api/v1/connections/status", [
                'externalCustomerId' => (string) $externalCustomerId,
            ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ($data['status'] ?? null) !== 'success') {
            return null;
        }

        return $data;
    }

    /**
     * Token firmato a breve scadenza per il widget di collegamento (vedi
     * FusionWA: src/lib/widget-token.ts): sostituisce l'apiKey pubblica + il
     * tenantId numerico passati finora al widget, che da soli permettevano a
     * chi indovinava un tenantId di leggere o sostituire la connessione
     * WhatsApp di un altro cliente. Va chiamato server-to-server (qui, non
     * nel browser) subito prima di renderizzare EmbeddedSignupButton.
     * Ritorna null se la richiesta fallisce o FusionWA non dà un token.
     */
    public function getWidgetToken(string|int $externalCustomerId): ?string
    {
        $response = Http::withHeaders([
            'x-fusionwa-api-key' => config('services.fusionwa.api_key'),
            'x-fusionwa-api-secret' => config('services.fusionwa.api_secret'),
        ])
            ->timeout(10)
            ->post("{$this->baseUrl}/api/v1/widget/token", [
                'customerId' => (string) $externalCustomerId,
            ]);

        if ($response->failed()) {
            return null;
        }

        $token = $response->json('token');

        return is_string($token) ? $token : null;
    }

    /**
     * True solo se la connessione riportata da FusionWA per questo customer
     * è attiva, operativa, e corrisponde esattamente (waba_id, phone_number_id,
     * phone_number) a quella che si vuole salvare — non un confronto parziale.
     */
    public function matches(array $status, array $claimed): bool
    {
        $connection = $status['connection'] ?? null;

        return ($status['customerStatus'] ?? null) === 'ACTIVE'
            && is_array($connection)
            && ($connection['status'] ?? null) === 'CONNECTED'
            && ($connection['operational'] ?? false) === true
            && ($connection['wabaId'] ?? null) === ($claimed['waba_id'] ?? null)
            && ($connection['phoneNumberId'] ?? null) === ($claimed['phone_number_id'] ?? null)
            && ($connection['phoneNumber'] ?? null) === ($claimed['phone_number'] ?? null);
    }
}
