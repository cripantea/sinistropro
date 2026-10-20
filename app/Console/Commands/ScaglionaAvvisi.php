<?php

namespace App\Console\Commands;

use App\Models\Pratica;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Avvisi arretrati (data già passata, sinistri aperti) distribuiti su più giorni lavorativi
 * invece di partire tutti insieme al prossimo giro delle 07:00. Non invia niente: sposta solo le date.
 */
class ScaglionaAvvisi extends Command
{
    protected $signature = 'app:scagliona-avvisi
                            {--giorni=4 : Su quanti giorni lavorativi distribuirli}
                            {--da= : Primo giorno (AAAA-MM-GG), default oggi}
                            {--tenant= : Solo un tenant (ID)}
                            {--dry-run : Mostra cosa cambierebbe senza modificare nulla}';

    protected $description = 'Distribuisce gli avvisi scaduti dei sinistri aperti su più giorni lavorativi (senza inviare email).';

    public function handle(): int
    {
        $giorni = max(1, (int) $this->option('giorni'));
        $dry    = (bool) $this->option('dry-run');
        $primo  = $this->option('da') ? Carbon::parse($this->option('da'))->startOfDay() : today();

        // Giorni lavorativi a partire dal primo (sabato e domenica saltati).
        $date = [];
        for ($d = $primo->copy(); count($date) < $giorni; $d->addDay()) {
            if (! $d->isWeekend()) {
                $date[] = $d->toDateString();
            }
        }

        // Prima i più vecchi.
        $pratiche = Pratica::acrossAllTenants()
            ->whereDate('data_prossimo_avviso', '<', $primo)
            ->where(fn (Builder $q) => $q->whereNull('current_status_id')
                ->orWhereHas('currentStatus', fn (Builder $s) => $s->where('is_closed', false)))
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', (int) $t))
            ->orderBy('data_prossimo_avviso')->orderBy('id')
            ->get(['id', 'data_prossimo_avviso']);

        if ($pratiche->isEmpty()) {
            $this->info('Nessun avviso arretrato.');
            return self::SUCCESS;
        }

        $perGiorno = (int) ceil($pratiche->count() / $giorni);
        $conteggio = [];

        foreach ($pratiche->values() as $i => $p) {
            $nuova = $date[intdiv($i, $perGiorno)];
            $conteggio[$nuova] = ($conteggio[$nuova] ?? 0) + 1;
            $this->line(sprintf('#%d: %s → %s', $p->id, $p->data_prossimo_avviso->toDateString(), $nuova));
            if (! $dry) {
                Pratica::acrossAllTenants()->where('id', $p->id)->update(['data_prossimo_avviso' => $nuova]);
            }
        }

        $this->newLine();
        foreach ($conteggio as $data => $n) {
            $this->info(Carbon::parse($data)->locale('it')->isoFormat('dddd D/MM') . ": {$n} avvisi");
        }
        $this->info(($dry ? '[DRY-RUN, niente modificato] ' : '') . $pratiche->count() . ' avvisi distribuiti su ' . count($conteggio) . ' giorni.');

        return self::SUCCESS;
    }
}
