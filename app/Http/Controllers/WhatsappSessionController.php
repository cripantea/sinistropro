<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappSessionController extends Controller
{
    public function index(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $session = WhatsappSession::where('tenant_id', $tenantId)->first();

        return Inertia::render('Whatsapp/Index', [
            'session' => [
                'status'      => $session?->status ?? 'pending',
                'phoneNumber' => $session?->display_phone_number,
            ],
            // Chiave pubblica del plugin FusionWA (safe lato browser, non è il
            // secret): il widget la usa per aprire l'Embedded Signup di Meta.
            'fusionwaApiKey'  => config('services.fusionwa.api_key'),
            'fusionwaBaseUrl' => config('services.fusionwa.base_url'),
        ]);
    }
}
