<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use App\Services\WhatsappServiceClient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappSessionController extends Controller
{
    public function __construct(private WhatsappServiceClient $client)
    {
    }

    public function index(): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $session = WhatsappSession::where('tenant_id', $tenantId)->first();

        return Inertia::render('Whatsapp/Index', [
            'session' => [
                'status'      => $session?->status ?? 'disconnected',
                'phoneNumber' => $session?->phone_number,
                'qrCode'      => $session?->qr_code,
            ],
        ]);
    }

    public function start(): RedirectResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403, 'Solo il tenant-admin può collegare il numero WhatsApp.');

        $this->client->startSession($authed->tenant_id);

        return redirect()->route('whatsapp.index')
            ->with('success', 'Connessione avviata: scansiona il QR code con WhatsApp.');
    }

    public function stop(): RedirectResponse
    {
        $authed = auth()->user();
        abort_unless($authed->isTenantAdmin(), 403, 'Solo il tenant-admin può disconnettere il numero WhatsApp.');

        $this->client->stopSession($authed->tenant_id);

        return redirect()->route('whatsapp.index')
            ->with('success', 'Disconnessione avviata.');
    }
}
