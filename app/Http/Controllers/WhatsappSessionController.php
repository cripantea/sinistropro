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
        [$status, $qrCode] = $this->reconcileStatus($tenantId, $session);

        return Inertia::render('Whatsapp/Index', [
            'session' => [
                'status'      => $status,
                'phoneNumber' => $session?->phone_number,
                'qrCode'      => $status === 'qr' ? $qrCode : null,
            ],
        ]);
    }

    /**
     * Il database riflette solo l'ultimo evento ricevuto via webhook: se il
     * microservizio si riavvia (deploy, crash) senza passare da un evento di
     * disconnessione pulito, la riga puó restare "congelata" su uno stato
     * (es. connected/qr) che non corrisponde più a nessun processo reale.
     * Per evitare di mostrare uno stato inventato (e magari nascondere il
     * bottone "Connetti" quando servirebbe), interroghiamo lo stato live del
     * servizio ad ogni caricamento pagina e, se diverge dal DB, ci fidiamo
     * di quello reale e correggiamo la riga.
     *
     * @return array{0: string, 1: ?string}
     */
    private function reconcileStatus(int $tenantId, ?WhatsappSession $session): array
    {
        $dbStatus = $session?->status ?? 'disconnected';

        try {
            $live = $this->client->status($tenantId);
        } catch (\Throwable $e) {
            // Servizio irraggiungibile: meglio fidarsi dell'ultimo stato noto
            // che rompere la pagina.
            return [$dbStatus, $session?->qr_code];
        }

        $liveStatus = strtolower((string) ($live['status'] ?? ''));
        $validStatuses = ['disconnected', 'starting', 'qr', 'connected'];
        if (! in_array($liveStatus, $validStatuses, true)) {
            $liveStatus = 'disconnected';
        }

        if ($liveStatus !== $dbStatus) {
            $session = WhatsappSession::updateOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'status'  => $liveStatus,
                    'qr_code' => $liveStatus === 'qr' ? ($live['qr'] ?? null) : null,
                ]
            );
        }

        return [$liveStatus, $live['qr'] ?? $session?->qr_code];
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
