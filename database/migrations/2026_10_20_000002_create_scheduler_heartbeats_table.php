<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ultima esecuzione dei comandi automatici: se si fermano, l'app lo mostra il giorno stesso. */
    public function up(): void
    {
        Schema::create('scheduler_heartbeats', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('ran_at');
        });

        // Si parte da adesso: se il prossimo giro delle 07:00 non arriva, scatta l'avviso.
        DB::table('scheduler_heartbeats')->insert(['name' => 'avvisi', 'ran_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_heartbeats');
    }
};
