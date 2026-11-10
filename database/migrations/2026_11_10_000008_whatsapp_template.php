<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Template WhatsApp approvati da Meta (gestiti via FusionWA): servono per scrivere fuori dalla finestra di 24 ore.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('name', 512);
            $table->string('language', 10)->default('it');
            $table->string('category', 20)->default('UTILITY');
            $table->string('status', 20)->default('PENDING');
            $table->text('status_reason')->nullable();
            $table->text('body');                 // come lo vede Meta: {{1}}, {{2}}…
            $table->text('corpo')->nullable();    // come l'ha scritto l'agenzia: {nome_cliente}…
            $table->json('variabili')->nullable(); // {"1": "{nome_cliente}", "2": "{numero_pratica}"}
            $table->unsignedTinyInteger('variable_count')->default(0);
            $table->string('header_text', 60)->nullable();
            $table->string('footer_text', 60)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name', 'language']);
        });

        Schema::table('automations', function (Blueprint $table) {
            $table->foreignId('whatsapp_template_id')->nullable()->after('channel')->constrained('whatsapp_templates')->nullOnDelete();
            $table->json('whatsapp_params')->nullable()->after('whatsapp_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_template_id');
            $table->dropColumn('whatsapp_params');
        });
        Schema::dropIfExists('whatsapp_templates');
    }
};
