<?php

namespace App\Jobs;

use App\Mail\AvvisoPraticaAperta;
use App\Models\EmailLog;
use App\Models\Pratica;
use App\Models\User;
use App\Services\TenantMailerResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

    public function handle(TenantMailerResolver $mailer): void
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

        $logContext  = ['tipo' => 'avviso', 'pratica_id' => $pratica->id];
        $destinatari = $this->raccogliDestinatari($pratica);

        if ($destinatari->isEmpty()) {
            // Nessun destinatario valido: lo scriviamo nel registro. La data NON avanza,
            // così l'avviso viene ritentato al prossimo giro invece di sparire.
            $mailer->registraSaltata($pratica->tenant_id, 'Nessun destinatario valido (creatore assente/disattivato/senza email e nessun amministratore attivo).', $logContext);
            Log::warning('AvvisoPratica: nessun destinatario', ['pratica_id' => $pratica->id]);

            return;
        }

        $inviati = 0;
        $falliti = [];

        foreach ($destinatari as $destinatario) {
            // Idempotenza: se un tentativo precedente (retry del job) ha già consegnato oggi
            // a questo indirizzo, non reinviamo.
            if ($this->giaInviatoOggi($pratica->id, $destinatario->email)) {
                $inviati++;
                continue;
            }

            try {
                $mailer->send(
                    $tenant,
                    $destinatario->email,
                    new AvvisoPraticaAperta($pratica, $destinatario, $nuovaData),
                    [],
                    $logContext
                );
                $inviati++;
            } catch (\Throwable $e) {
                // Un destinatario che fallisce non deve impedire l'invio agli altri
                // (l'errore è già nel registro email dal TenantMailerResolver).
                $falliti[$destinatario->email] = $e->getMessage();
                Log::error('AvvisoPratica: errore invio email', [
                    'pratica_id'   => $pratica->id,
                    'destinatario' => $destinatario->email,
                    'errore'       => $e->getMessage(),
                ]);
            }
        }

        // Nessuna consegna: rilancio per far ritentare il job (la data non avanza).
        if ($inviati === 0) {
            throw new \RuntimeException('Avviso pratica #' . $pratica->id . ' non consegnato a nessun destinatario: ' . json_encode($falliti));
        }

        // --- Aggiornamento data (almeno un destinatario raggiunto) ---

        // Usiamo update() diretto per evitare che i global scope (se mai attivi)
        // o gli observer interferiscano con l'operazione di sistema.
        Pratica::acrossAllTenants()
            ->where('id', $pratica->id)
            ->update(['data_prossimo_avviso' => $nuovaData->toDateString()]);

        Log::info('AvvisoPratica: promemoria inviato', [
            'pratica_id'        => $pratica->id,
            'tenant'            => $tenant->name,
            'nuova_data_avviso' => $nuovaData->toDateString(),
            'destinatari'       => $destinatari->pluck('email'),
            'falliti'           => array_keys($falliti),
        ]);
    }

    private function giaInviatoOggi(int $praticaId, string $email): bool
    {
        return EmailLog::acrossAllTenants()
            ->where('pratica_id', $praticaId)
            ->where('tipo', 'avviso')
            ->where('status', 'sent')
            ->where('to_address', $email)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }

    /**
     * Destinatari unici (per indirizzo): creatore + TUTTI gli amministratori attivi del
     * tenant. Si escludono utenti disattivati o senza email valida.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function raccogliDestinatari(Pratica $pratica): \Illuminate\Support\Collection
    {
        return collect([$pratica->utenteCreatore])
            ->merge(User::tenantAdmin($pratica->tenant_id)->get())
            ->filter(fn (?User $u) => $u && $u->is_active && filter_var($u->email, FILTER_VALIDATE_EMAIL))
            ->unique(fn (User $u) => strtolower($u->email))
            ->values();
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('AvvisoPratica: job fallito definitivamente', [
            'pratica_id' => $this->praticaId,
            'errore'     => $exception->getMessage(),
        ]);

        $pratica = Pratica::acrossAllTenants()->find($this->praticaId);
        if ($pratica) {
            EmailLog::registra([
                'tenant_id'  => $pratica->tenant_id,
                'pratica_id' => $pratica->id,
                'tipo'       => 'avviso',
                'status'     => 'failed',
                'error'      => 'Job avviso fallito dopo tutti i tentativi: ' . mb_substr($exception->getMessage(), 0, 1500),
            ]);
        }
    }
}
