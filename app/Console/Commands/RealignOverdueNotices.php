<?php

namespace App\Console\Commands;

use App\Models\Pratica;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RealignOverdueNotices extends Command
{
    protected $signature = 'app:realign-overdue-notices
                            {--dry-run : Mostra cosa cambierebbe senza modificare nulla}
                            {--tenant= : Solo un tenant (ID)}';

    protected $description = 'Sposta le date di prossimo avviso già passate (pratiche aperte) a oggi + giorni di avviso del tenant, SENZA inviare email.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $n = 0;

        Pratica::acrossAllTenants()
            ->with('tenant')
            ->whereDate('data_prossimo_avviso', '<', today())
            ->where(fn (Builder $q) => $q->whereNull('current_status_id')
                ->orWhereHas('currentStatus', fn (Builder $s) => $s->where('is_closed', false)))
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_id', (int) $t))
            ->chunkById(200, function ($pratiche) use ($dry, &$n): void {
                foreach ($pratiche as $p) {
                    $nuova = now()->addDays($p->tenant->getDefaultNoticeDays())->toDateString();
                    $this->line(sprintf('#%d (%s): %s → %s', $p->id, $p->tenant->name, $p->data_prossimo_avviso->toDateString(), $nuova));
                    if (! $dry) {
                        Pratica::acrossAllTenants()->where('id', $p->id)->update(['data_prossimo_avviso' => $nuova]);
                    }
                    $n++;
                }
            });

        $this->info(($dry ? '[DRY-RUN] ' : '')."{$n} pratiche riallineate.");

        return self::SUCCESS;
    }
}
