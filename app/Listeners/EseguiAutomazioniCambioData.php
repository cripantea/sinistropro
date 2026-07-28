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
            if ($automation->requires_confirmation && $event->skipConfirmableAutomations) {
                continue;
            }

            ExecuteAutomationJob::dispatch($event->pratica, $automation);
            $dispatched[] = $automation->id;
        }

        Log::info('EseguiAutomazioniCambioData: dispatched jobs', [
            'pratica_id' => $event->pratica->id,
            'field' => $event->fieldName,
            'automations' => $dispatched,
        ]);
    }
}
