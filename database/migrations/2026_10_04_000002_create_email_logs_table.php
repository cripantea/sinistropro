<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro di ogni email di sistema (avvisi, automazioni, notifiche stato):
        // inviata, fallita o saltata, con il motivo — prima l'unica traccia era laravel.log.
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pratica_id')->nullable()->constrained('pratiche')->nullOnDelete();
            $table->foreignId('automation_id')->nullable()->constrained('automations')->nullOnDelete();
            $table->string('tipo', 40);              // automazione | avviso | stato | test
            $table->string('to_address')->nullable();
            $table->json('cc_addresses')->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 10);            // sent | failed | skipped
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
