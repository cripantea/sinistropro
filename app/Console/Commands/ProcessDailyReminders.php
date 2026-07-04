<?php

namespace App\Console\Commands;

use App\Jobs\InviaEmailAvvisoPratica;
use App\Models\Pratica;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class ProcessDailyReminders extends Command
{
    protected $signature = 'app:process-daily-reminders
                            {--dry-run : Simula l\'esecuzione senza inviare email né aggiornare il DB}
                            {--tenant= : Esegui solo per un tenant specifico (ID)}';

    protected $description = 'Processa i promemoria giornalieri: mette in coda le email per le pratiche aperte con data_prossimo_avviso = oggi.';

    public function handle(): int
    {
        $oggi      = today();
        $isDryRun  = $this->option('dry-run');
        $tenantId  = $this->option('tenant');

        $this->info("▶ Avvio ProcessDailyReminders — data: {$oggi->toDateString()}" . ($isDryRun ? ' [DRY-RUN]' : ''));

        $dispatched = 0;
        $skipped    = 0;

        // acrossAllTenants() bypassa esplicitamente il TenantScope.
        // Necessario: questo comando gira come sistema, senza un utente autenticato.
        // (Il TenantScope esce già in anticipo quando auth()->check() è false,
        //  ma lo bypassiamo esplicitamente per rendere l'intento evidente.)
        $query = Pratica::acrossAllTenants()
            ->with(['tenant', 'utenteCreatore', 'currentStatus'])
            ->whereDate('data_prossimo_avviso', $oggi)
            ->where(function (Builder $q): void {
                // Include pratiche senza status + quelle con status non chiuso.
                $q->whereNull('current_status_id')
                  ->orWhereHas('currentStatus', fn (Builder $sq) => $sq->where('is_closed', false));
            });

        if ($tenantId) {
            $query->where('tenant_id', (int) $tenantId);
            $this->info("   → Filtro tenant_id: {$tenantId}");
        }

        $query->chunk(100, function ($pratiche) use ($isDryRun, &$dispatched, &$skipped): void {
            foreach ($pratiche as $pratica) {
                if (! $pratica->utenteCreatore) {
                    $this->warn("   ⚠ Pratica #{$pratica->id}: nessun utente creatore, saltata.");
                    $skipped++;
                    continue;
                }

                if ($isDryRun) {
                    $this->line("   [DRY-RUN] Pratica #{$pratica->id} ({$pratica->tenant->name}) → job NON accodato.");
                    $dispatched++;
                    continue;
                }

                InviaEmailAvvisoPratica::dispatch($pratica->id);
                $dispatched++;
            }
        });

        $summary = [
            'data'       => $oggi->toDateString(),
            'dispatched' => $dispatched,
            'skipped'    => $skipped,
            'dry_run'    => $isDryRun,
        ];

        $this->info("✔ Completato: {$dispatched} job accodati, {$skipped} pratiche saltate.");
        Log::info('ProcessDailyReminders completato', $summary);

        return Command::SUCCESS;
    }
}
