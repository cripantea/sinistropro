<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('tenant_status_id')
                  ->nullable()
                  ->constrained('tenant_statuses')
                  ->nullOnDelete();
            $table->enum('channel', ['email', 'whatsapp', 'both'])->default('email');
            $table->enum('recipient', ['cliente', 'perito', 'gestore'])->default('cliente');
            $table->text('message_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index('tenant_status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
