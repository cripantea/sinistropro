<?php

namespace App\Console\Commands;

use App\Models\AutomationApproval;
use App\Models\Automation;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scansiona ogni tenant con la feature `clienti` abilitata e, per ogni automazione
 * di tipo `cliente_date_field` attiva, trova i clienti il cui campo data corrisponde
 * a (oggi + days_before) e dispatcha ExecuteClienteAutomationJob.
 *
 * Esempio:
 *   - automation: watched_field=scadenza_patente, days_before=30, channel=whatsapp
 *   - oggi=2026-09-29 → cerca clienti con scadenza_patente=2026-10-29
 */
class ProcessClienteDateReminders extends Command
{
    protected $signature = 'app:process-cliente-date-reminders
                            {--dry-run : Simula senza accodare job}
                            {--tenant= : Esegui solo per un tenant specifico (ID)}';

    protected $description = 'Crea i promemoria da confermare (WA/email) per scadenze date nei campi cliente.';

    public function handle(): int
    {
        $oggi     = today();
        $isDry    = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $this->info("▶ ProcessClienteDateReminders — {$oggi->toDateString()}" . ($isDry ? ' [DRY-RUN]' : ''));

        $dispatched = 0;
        $skipped    = 0;

        $tenantsQuery = Tenant::query();
        if ($tenantId) {
            $tenantsQuery->where('id', (int) $tenantId);
        }

        $tenantsQuery->chunk(50, function ($tenants) use ($oggi, $isDry, &$dispatched, &$skipped): void {
            foreach ($tenants as $tenant) {
                if (! $tenant->hasFeature('clienti')) {
                    continue;
                }

                $automations = Automation::where('tenant_id', $tenant->id)
                    ->where('trigger_type', 'cliente_date_field')
                    ->where('is_active', true)
                    ->get();

                if ($automations->isEmpty()) {
                    continue;
                }

                foreach ($automations as $automation) {
                    $targetDate = $oggi->copy()->addDays($automation->days_before)->toDateString();
                    $field      = $automation->watched_field;

                    if (! $field) {
                        continue;
                    }

                    // Carica tutti i clienti del tenant e filtra in PHP per compatibilità SQLite.
                    // In MySQL/prod si potrebbe usare JSON_EXTRACT per un indice più efficiente.
                    Cliente::acrossAllTenants()
                        ->where('tenant_id', $tenant->id)
                        ->whereNotNull('custom_fields')
                        ->chunk(100, function ($clienti) use ($automation, $field, $targetDate, $isDry, &$dispatched, &$skipped): void {
                            foreach ($clienti as $cliente) {
                                $value = $cliente->custom_fields[$field] ?? null;

                                if ($value !== $targetDate) {
                                    $skipped++;
                                    continue;
                                }

                                if ($isDry) {
                                    $this->line("   [DRY-RUN] Cliente #{$cliente->id} ({$cliente->nome}) → automation #{$automation->id} NON messa in conferma.");
                                    $dispatched++;
                                    continue;
                                }

                                // Nessun invio automatico: il promemoria resta "da confermare" finché
                                // un utente non ne rivede messaggio e destinatari (una sola volta per scadenza).
                                $approval = AutomationApproval::acrossAllTenants()->firstOrCreate([
                                    'automation_id' => $automation->id,
                                    'cliente_id'    => $cliente->id,
                                    'field_name'    => $field,
                                    'field_value'   => $targetDate,
                                ], ['tenant_id' => $cliente->tenant_id]);

                                if ($approval->wasRecentlyCreated) {
                                    $dispatched++;
                                }
                            }
                        });
                }
            }
        });

        $this->info("✔ Completato: {$dispatched} promemoria in attesa di conferma, {$skipped} clienti saltati.");

        Log::info('ProcessClienteDateReminders completato', [
            'data'       => $oggi->toDateString(),
            'dispatched' => $dispatched,
            'skipped'    => $skipped,
            'dry_run'    => $isDry,
        ]);

        return Command::SUCCESS;
    }
}
