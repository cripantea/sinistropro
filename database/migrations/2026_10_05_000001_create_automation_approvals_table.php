<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Promemoria programmati (es. scadenza patente del cliente) che scattano senza un
        // utente davanti allo schermo: invece di partire da soli restano "da confermare".
        Schema::create('automation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clienti')->cascadeOnDelete();
            $table->string('field_name');
            $table->string('field_value', 40);          // data che ha fatto scattare il promemoria
            $table->string('status', 12)->default('pending'); // pending | sent | discarded
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Un solo promemoria per automazione + cliente + scadenza, anche se il comando gira più volte.
            $table->unique(['automation_id', 'cliente_id', 'field_name', 'field_value'], 'automation_approvals_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_approvals');
    }
};
