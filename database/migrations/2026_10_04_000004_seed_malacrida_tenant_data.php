<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Intenzionalmente vuota: i dati specifici di Malacrida (carrozzeria Re-nova,
    // compagnie) NON vengono applicati al deploy per non toccare il tenant.
    // Si possono applicare a mano: php artisan db:seed --class=MalacridaTenantSeeder
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
