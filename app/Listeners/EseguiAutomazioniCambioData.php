<?php

namespace App\Listeners;

use App\Events\PraticaCampoDataAggiornato;
use App\Jobs\ExecuteAutomationJob;
use App\Models\Automation;
use Illuminate\Support\Facades\Log;

class EseguiAutomazioniCambioData
{
    /**
     * Gira in sincrono durante la richiesta HTTP: legge lo stato corrente del DB
     * e dispatcha un job asincrono per ogni automazione trovata.
     * Solo ExecuteAutomationJob è asincrono (I/O pesante: email, WhatsApp).
     */
    public function handle(PraticaCampoDataAggiornato $event): void
    {
        $automations = Automation::where('tenant_id', $event->pratica->tenant_id)
            ->where('trigger_type', 'date_field')
            ->where('watched_field', $event->fieldName)
            ->where('is_active', true)
            ->get();

        if ($automations->isEmpty()) {
            return;
        }

        $dispatched = [];

        foreach ($automations as $automation) {
            // L'utente ha scelto "procedi senza automazioni" nella finestra di conferma.
            if ($event->skipConfirmableAutomations) {
                continue;
            }

            // Scelte fatte nella finestra di conferma: automazione esclusa, oppure
            // destinatari/CC modificati rispetto a quelli calcolati.
            $override = $event->overrides[$automation->id] ?? null;
            if ($override && ! $override['send']) {
                continue;
            }

            ExecuteAutomationJob::dispatch(
                $event->pratica,
                $automation,
                $override ? ['recipients' => $override['recipients'], 'cc' => $override['cc']] : null
            );
            $dispatched[] = $automation->id;
        }

        Log::info('EseguiAutomazioniCambioData: dispatched jobs', [
            'pratica_id' => $event->pratica->id,
            'field' => $event->fieldName,
            'automations' => $dispatched,
        ]);
    }
}
