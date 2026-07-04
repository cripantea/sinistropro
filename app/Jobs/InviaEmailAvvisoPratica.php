<?php

namespace App\Jobs;

use App\Mail\AvvisoPraticaAperta;
use App\Models\Pratica;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InviaEmailAvvisoPratica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // secondi tra un retry e l'altro

    public function __construct(public readonly int $praticaId)
    {
        // Passiamo solo l'ID, non il modello: evita serializzazione pesante
        // e ricarica dati freschi quando il worker esegue il job.
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        // Il worker gira senza auth() attivo → TenantScope non si applica.
        // Carichiamo le relazioni necessarie in un'unica query eager.
        $pratica = Pratica::acrossAllTenants()
            ->with(['tenant', 'utenteCreatore', 'currentStatus'])
            ->findOrFail($this->praticaId);

        $tenant         = $pratica->tenant;
        $giorni         = $tenant->getDefaultNoticeDays();
        $nuovaData      = now()->addDays($giorni)->startOfDay();

        // --- Invio email ---

        $destinatari = $this->raccogliDestinatari($pratica);

        foreach ($destinatari as $destinatario) {
            try {
                Mail::to($destinatario)->send(
                    new AvvisoPraticaAperta($pratica, $destinatario, $nuovaData)
                );
            } catch (\Throwable $e) {
                Log::error('AvvisoPratica: errore invio email', [
                    'pratica_id'     => $pratica->id,
                    'destinatario'   => $destinatario->email,
                    'errore'         => $e->getMessage(),
                ]);
                // Rilancio per far ritentare il job interamente.
                throw $e;
            }
        }

        // --- Aggiornamento data (solo dopo invio avvenuto) ---

        // Usiamo update() diretto per evitare che i global scope (se mai attivi)
        // o gli observer interferiscano con l'operazione di sistema.
        Pratica::acrossAllTenants()
            ->where('id', $pratica->id)
            ->update(['data_prossimo_avviso' => $nuovaData->toDateString()]);

        Log::info('AvvisoPratica: promemoria inviato', [
            'pratica_id'       => $pratica->id,
            'tenant'           => $tenant->name,
            'nuova_data_avviso' => $nuovaData->toDateString(),
            'destinatari'      => $destinatari->pluck('email'),
        ]);
    }

    /**
     * Raccoglie i destinatari unici: creatore + tenant-admin (se diverso).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function raccogliDestinatari(Pratica $pratica): \Illuminate\Support\Collection
    {
        $destinatari = collect([$pratica->utenteCreatore]);

        $admin = User::tenantAdmin($pratica->tenant_id)
            ->where('id', '!=', $pratica->utente_creatore_id)
            ->first();

        if ($admin) {
            $destinatari->push($admin);
        }

        return $destinatari;
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('AvvisoPratica: job fallito definitivamente', [
            'pratica_id' => $this->praticaId,
            'errore'     => $exception->getMessage(),
        ]);
    }
}
