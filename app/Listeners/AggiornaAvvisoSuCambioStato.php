<?php

namespace App\Listeners;

use App\Events\PraticaStatoAggiornato;
use App\Models\Pratica;

/**
 * Cambiando stato il conto alla rovescia dell'avviso riparte con i giorni del nuovo stato
 * (Impostazioni → Stati, es. "Documenti mancanti" ogni 3 giorni, "Attesa liquidazione" ogni 30).
 */
class AggiornaAvvisoSuCambioStato
{
    public function handle(PraticaStatoAggiornato $event): void
    {
        if (! $event->newStatusId || (int) $event->oldStatusId === (int) $event->newStatusId) {
            return;
        }

        $pratica = $event->pratica;
        $tenant = $pratica->tenant()->first();
        if (! $tenant) {
            return;
        }

        Pratica::acrossAllTenants()->whereKey($pratica->id)->update([
            'data_prossimo_avviso' => now()->addDays($tenant->noticeDaysFor((int) $event->newStatusId))->toDateString(),
        ]);
    }
}
