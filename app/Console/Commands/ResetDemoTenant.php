<?php

namespace App\Console\Commands;

use App\Models\Allegato;
use App\Models\Ispezione;
use App\Models\Pratica;
use App\Models\PraticaNota;
use App\Models\Tenant;
use App\Models\TenantStatus;
use App\Models\User;
use Database\Seeders\DemoTenantSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemoTenant extends Command
{
    protected $signature   = 'demo:reset {--force : Skip confirmation prompt}';
    protected $description = 'Wipe and re-seed the "Studio Demo" tenant to its initial demo state';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Reset the Studio Demo tenant? All changes will be lost.')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $tenant = Tenant::where('name', DemoTenantSeeder::TENANT_NAME)->first();

        if ($tenant) {
            $this->info("Wiping tenant #{$tenant->id} — " . DemoTenantSeeder::TENANT_NAME);

            DB::transaction(function () use ($tenant) {
                $praticaIds = Pratica::where('tenant_id', $tenant->id)->pluck('id');

                Allegato::whereIn('pratica_id', $praticaIds)->delete();
                Ispezione::where('tenant_id', $tenant->id)->delete();
                PraticaNota::whereIn('pratica_id', $praticaIds)->delete();
                Pratica::where('tenant_id', $tenant->id)->delete();
                TenantStatus::where('tenant_id', $tenant->id)->delete();

                // Delete all tenant users before deleting the tenant to avoid ON DELETE SET NULL
                // leaving orphan users with tenant_id = null
                User::where('tenant_id', $tenant->id)->delete();

                // Also purge known demo emails that may have been orphaned by a prior broken reset
                User::whereIn('email', [DemoTenantSeeder::ADMIN_EMAIL, 'perito@kryptodoc.it'])
                    ->where('role', '!=', 'superadmin')
                    ->delete();

                $tenant->delete();
            });

            $this->info('Tenant wiped.');
        } else {
            $this->info('No existing demo tenant found — will create fresh.');
        }

        $this->call('db:seed', ['--class' => DemoTenantSeeder::class]);
        $this->info('Studio Demo tenant re-seeded successfully.');

        return self::SUCCESS;
    }
}
