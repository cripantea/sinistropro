<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_history_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                  ->constrained('tenants')
                  ->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')
                  ->constrained('whatsapp_sessions')
                  ->cascadeOnDelete();
            $table->enum('sync_type', ['smb_app_state_sync', 'history']);
            $table->unsignedTinyInteger('phase')->nullable();
            $table->enum('status', ['requested', 'in_progress', 'completed', 'failed'])->default('requested');
            $table->unsignedInteger('progress')->nullable();
            $table->unsignedBigInteger('last_chunk_order')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_session_id', 'sync_type', 'phase'], 'whatsapp_history_syncs_session_type_phase_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_history_syncs');
    }
};
