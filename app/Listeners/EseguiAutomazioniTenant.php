<?php

namespace App\Listeners;

use App\Events\PraticaStatoAggiornato;
use App\Jobs\ExecuteAutomationJob;
use App\Models\Automation;
use Illuminate\Support\Facades\Log;

class EseguiAutomazioniTenant
{
    /**
     * Gira in sincrono durante la richiesta HTTP: legge lo stato corrente del DB
     * e dispatcha un job asincrono per ogni automazione trovata.
     * Solo ExecuteAutomationJob è asincrono (I/O pesante: email, WhatsApp).
     */
    public function handle(PraticaStatoAggiornato $event): void
    {
        $automations = Automation::where('tenant_id', $event->pratica->tenant_id)
            ->where('tenant_status_id', $event->newStatusId)
            ->where('is_active', true)
            ->get();

        if ($automations->isEmpty()) {
            return;
        }

        foreach ($automations as $automation) {
            ExecuteAutomationJob::dispatch($event->pratica, $automation);
        }

        Log::info('EseguiAutomazioniTenant: dispatched jobs', [
            'pratica_id'    => $event->pratica->id,
            'new_status_id' => $event->newStatusId,
            'automations'   => $automations->pluck('id'),
        ]);
    }

}
