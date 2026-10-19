<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Storico degli import clienti: il file resta su disco solo finché l'import
        // non è concluso, qui restano mappatura e contatori.
        Schema::create('importazioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_id', 36);
            $table->string('stato', 20)->default('bozza'); // bozza | in_coda | in_corso | completata | fallita
            $table->json('mapping')->nullable();
            $table->json('opzioni')->nullable();
            $table->json('riepilogo')->nullable();
            $table->json('avvisi')->nullable();
            $table->text('errore')->nullable();
            $table->timestamp('completata_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importazioni');
    }
};
