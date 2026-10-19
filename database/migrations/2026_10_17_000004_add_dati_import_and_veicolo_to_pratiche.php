<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Valori arrivati dall'ultimo import: se oggi il campo è ancora uguale a quel valore
        // nessuno l'ha corretto a mano, quindi un import successivo può aggiornarlo.
        Schema::table('clienti', function (Blueprint $table) {
            $table->json('dati_import')->nullable()->after('custom_fields');
        });
        Schema::table('veicoli', function (Blueprint $table) {
            $table->json('dati_import')->nullable()->after('custom_fields');
        });

        Schema::table('pratiche', function (Blueprint $table) {
            $table->foreignId('veicolo_id')->nullable()->after('cliente_id')->constrained('veicoli')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pratiche', function (Blueprint $table) {
            $table->dropConstrainedForeignId('veicolo_id');
        });
        Schema::table('veicoli', function (Blueprint $table) {
            $table->dropColumn('dati_import');
        });
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn('dati_import');
        });
    }
};
