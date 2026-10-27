<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use App\Services\FusionWaConnectionClient;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappSessionController extends Controller
{
    public function __construct(private readonly FusionWaConnectionClient $fusionWa) {}

    public function index(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $session = WhatsappSession::where('tenant_id', $tenantId)->first();

        return Inertia::render('Whatsapp/Index', [
            'session' => [
                'status'      => $session?->status ?? 'pending',
                'phoneNumber' => $session?->display_phone_number,
            ],
            // Token firmato a breve scadenza per il widget (server-to-server,
            // vedi FusionWaConnectionClient::getWidgetToken): sostituisce
            // l'apiKey pubblica, che da sola con un tenantId indovinato
            // permetteva di leggere/sostituire la connessione di un altro
            // cliente.
            'fusionwaToken'   => $this->fusionWa->getWidgetToken($tenantId),
            'fusionwaBaseUrl' => config('services.fusionwa.base_url'),
        ]);
    }
}
