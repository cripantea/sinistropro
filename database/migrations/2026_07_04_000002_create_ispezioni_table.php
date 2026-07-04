<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ispezioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pratica_id')->constrained('pratiche')->cascadeOnDelete();
            $table->foreignId('assegnato_a_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stato', 20)->default('pianificata');
            $table->dateTime('data_appuntamento')->nullable();
            $table->text('note_sopralluogo')->nullable();
            $table->json('dati_ispezione')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'pratica_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ispezioni');
    }
};
