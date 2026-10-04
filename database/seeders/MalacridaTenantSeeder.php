<?php

namespace Database\Seeders;

use App\Models\Contatto;
use App\Models\ListaValori;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Dati specifici del tenant Malacrida (le funzionalità generiche vivono nel codice,
 * qui solo ciò che riguarda questo studio): carrozzeria Re-nova e menu compagnie
 * all'apertura del sinistro. Idempotente: si può rieseguire senza duplicare nulla.
 *
 *   php artisan db:seed --class=MalacridaTenantSeeder
 */
class MalacridaTenantSeeder extends Seeder
{
    /** Ordine = ordine del menu a tendina "Compagnia" in Nuovo sinistro. */
    public const COMPAGNIE = ['Prima', 'Generali', 'AXA', 'Verti', 'Unipol'];

    public function run(): void
    {
        $tenants = Tenant::whereRaw('LOWER(name) LIKE ?', ['%malacrida%'])->get();

        if ($tenants->isEmpty()) {
            $this->command?->warn('Nessun tenant "Malacrida" trovato: nulla da fare.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->setup($tenant);
            $this->command?->info("Tenant \"{$tenant->name}\" (#{$tenant->id}) configurato.");
        }
    }

    private function setup(Tenant $tenant): void
    {
        // Carrozzeria Re-nova
        Contatto::acrossAllTenants()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'tipo' => 'carrozzeria', 'nome' => 'Re-nova'],
            ['telefono' => '3402148121', 'is_active' => true]
        );

        // Lista "Compagnie": letta dal menu in Nuovo sinistro. Se esiste già non la
        // sovrascrivo: potrebbe essere stata modificata dal tenant.
        ListaValori::acrossAllTenants()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'compagnie'],
            ['nome' => 'Compagnie', 'items' => self::COMPAGNIE]
        );

        // Abilita "Liste" nel menu così le compagnie restano modificabili dal tenant.
        $settings = $tenant->settings ?? [];
        data_set($settings, 'features.lista_personalizzate', true);
        $tenant->update(['settings' => $settings]);
    }
}
