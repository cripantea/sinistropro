<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            // Codice del cliente nel gestionale di provenienza: è la chiave con cui
            // un nuovo import riconosce i clienti già presenti.
            $table->string('codice_esterno', 50)->nullable()->after('tenant_id');
            $table->string('codice_fiscale', 16)->nullable()->after('email');
            $table->string('partita_iva', 20)->nullable()->after('codice_fiscale');
            $table->string('indirizzo')->nullable()->after('partita_iva');
            $table->string('cap', 10)->nullable()->after('indirizzo');
            $table->string('citta', 100)->nullable()->after('cap');
            $table->string('provincia', 5)->nullable()->after('citta');

            $table->unique(['tenant_id', 'codice_esterno']);
            $table->index(['tenant_id', 'codice_fiscale']);
            $table->index(['tenant_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'codice_esterno']);
            $table->dropIndex(['tenant_id', 'codice_fiscale']);
            $table->dropIndex(['tenant_id', 'nome']);
            $table->dropColumn(['codice_esterno', 'codice_fiscale', 'partita_iva', 'indirizzo', 'cap', 'citta', 'provincia']);
        });
    }
};
