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
        $status = $this->resolveStatus($session);

        return Inertia::render('Whatsapp/Index', [
            'session' => [
                'status'      => $status,
                'phoneNumber' => $session?->phone_number,
                'qrCode'      => $status === 'qr' ? $session?->qr_code : null,
            ],
        ]);
    }

    /**
     * Se il servizio si è bloccato/riavviato mentre la sessione era ancora in
     * fase di pairing (QR/starting), la riga in DB può restare "congelata" su
     * quello stato pur non avendo più un processo reale dietro. Un QR non
     * aggiornato da un po' non è più valido: lo trattiamo come disconnesso
     * invece di mostrare in eterno un codice morto.
     */
    private function resolveStatus(?WhatsappSession $session): string
    {
        if (! $session) {
            return 'disconnected';
        }

        $isPairing = in_array($session->status, ['starting', 'qr'], true);
        $stale = ($session->last_event_at ?? $session->updated_at)?->diffInMinutes(now()) >= 3;

        if ($isPairing && $stale) {
            return 'disconnected';
        }

        return $session->status;
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
