<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pratiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('utente_creatore_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('current_status_id')->nullable()->constrained('tenant_statuses')->nullOnDelete();
            $table->date('data_prossimo_avviso')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'data_prossimo_avviso']);
            $table->index(['tenant_id', 'current_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pratiche');
    }
};
