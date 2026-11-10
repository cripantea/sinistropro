<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteAutomationJob;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Ispezione;
use App\Models\Pratica;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Automazioni "Giorni prima/dopo una data del sinistro" (es. promemoria al cliente il giorno prima
 * della perizia): ogni mattina cerca i sinistri aperti la cui data cade a offset_days da oggi.
 * Ogni invio è registrato in automation_runs, così rilanciare il comando non manda doppioni.
 */
class ProcessPraticaDateReminders extends Command
{
    protected $signature = 'app:process-pratica-date-reminders {--dry-run} {--tenant=}';

    protected $description = 'Promemoria automatici X giorni prima/dopo una data del sinistro (perizia o campo data).';

    public function handle(): int
    {
        $oggi = today();
        $accodati = 0;

        $automazioni = Automation::query()
            ->where('trigger_type', 'date_reminder')
            ->where('is_active', true)
            ->whereNotNull('watched_field')
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', (int) $t))
            ->with('tenant')
            ->get();

        foreach ($automazioni as $automazione) {
            if (! $automazione->tenant?->hasAccess()) {
                continue;
            }

            // offset -1 = il giorno prima della data → la data cercata è domani.
            $data = $oggi->copy()->subDays($automazione->offset_days)->toDateString();

            foreach ($this->pratiche($automazione, $data) as $pratica) {
                if ($this->option('dry-run')) {
                    $this->line("[DRY-RUN] sinistro #{$pratica->id} → automazione #{$automazione->id}");
                    $accodati++;

                    continue;
                }

                if (AutomationRun::prima($automazione, 'pratica', $pratica->id, $data)) {
                    ExecuteAutomationJob::dispatch($pratica, $automazione);
                    $accodati++;
                }
            }
        }

        $this->info("Promemoria su date dei sinistri: {$accodati} accodati.");
        Log::info('ProcessPraticaDateReminders', ['data' => $oggi->toDateString(), 'accodati' => $accodati]);

        return self::SUCCESS;
    }

    /** Sinistri aperti dell'agenzia con la data osservata uguale a $data. */
    private function pratiche(Automation $automazione, string $data)
    {
        $base = Pratica::acrossAllTenants()
            ->where('tenant_id', $automazione->tenant_id)
            ->whereHas('currentStatus', fn ($q) => $q->where('is_closed', false));

        if ($automazione->watched_field === 'data_appuntamento') {
            // Conta l'ultimo sopralluogo del sinistro (quello mostrato nella scheda).
            return $base->get()->filter(function (Pratica $p) use ($data) {
                $ultimo = Ispezione::acrossAllTenants()->where('pratica_id', $p->id)->latest()->first();

                return $ultimo?->data_appuntamento?->toDateString() === $data;
            });
        }

        // Campo data personalizzato (JSON): filtro in PHP per restare compatibili con SQLite.
        return $base->whereNotNull('custom_fields')->get()
            ->filter(fn (Pratica $p) => substr((string) ($p->custom_fields[$automazione->watched_field] ?? ''), 0, 10) === $data);
    }
}
