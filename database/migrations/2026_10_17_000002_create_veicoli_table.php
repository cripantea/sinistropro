<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veicoli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clienti')->cascadeOnDelete();
            // La targa non è univoca (passaggi di proprietà, leasing); il telaio sì.
            $table->string('targa', 20)->nullable();
            $table->string('telaio', 30)->nullable();
            $table->string('marca', 50)->nullable();
            $table->string('modello', 100)->nullable();
            $table->string('descrizione')->nullable();
            $table->date('data_immatricolazione')->nullable();
            $table->date('scadenza_revisione')->nullable();
            $table->unsignedInteger('km')->nullable();
            $table->date('ultima_visita')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'telaio']);
            $table->index(['tenant_id', 'targa']);
            $table->index(['cliente_id', 'targa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veicoli');
    }
};
