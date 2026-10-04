<?php

use Database\Seeders\MalacridaTenantSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Applica al deploy i dati specifici di Malacrida (carrozzeria Re-nova, compagnie).
    // No-op se il tenant non esiste (es. ambienti locali/test).
    public function up(): void
    {
        (new MalacridaTenantSeeder())->run();
    }

    public function down(): void
    {
        // Dati di configurazione: nessun rollback automatico.
    }
};
