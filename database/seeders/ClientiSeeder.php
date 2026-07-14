<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ClientiSeeder extends Seeder
{
    private const CLIENTI_PER_TENANT = 20;

    public function run(): void
    {
        $faker = \Faker\Factory::create('it_IT');

        Tenant::all()->each(function (Tenant $tenant) use ($faker): void {
            for ($i = 0; $i < self::CLIENTI_PER_TENANT; $i++) {
                Cliente::create([
                    'tenant_id' => $tenant->id,
                    // unique() è scoped all'intera esecuzione del seeder: garantisce nomi
                    // univoci sia dentro lo stesso tenant sia tra tenant diversi.
                    'nome'      => $faker->unique()->name(),
                    'telefono'  => $faker->phoneNumber(),
                    'email'     => $faker->safeEmail(),
                ]);
            }
        });
    }
}
