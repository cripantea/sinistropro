<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Impostazioni dell'agenzia: giorni di avviso per stato e automazioni con oggetto, nuovi inneschi
// ("nuovo sinistro", "X giorni prima/dopo una data del sinistro") e registro anti-doppioni.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_statuses', function (Blueprint $table) {
            // Vuoto = giorni di avviso predefiniti dell'agenzia.
            $table->unsignedSmallInteger('notice_days')->nullable()->after('is_initial');
        });

        Schema::table('automations', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('channel');
            // Solo per "date_reminder": giorni rispetto alla data (negativo = prima, positivo = dopo).
            $table->smallInteger('offset_days')->default(0)->after('days_before');
        });

        // Un invio programmato (promemoria su data) parte una volta sola per sinistro/cliente e data.
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->string('soggetto', 10); // pratica | cliente
            $table->unsignedBigInteger('soggetto_id');
            $table->string('chiave', 40);   // es. la data che ha fatto scattare l'invio
            $table->timestamp('created_at')->nullable();
            $table->unique(['automation_id', 'soggetto', 'soggetto_id', 'chiave'], 'automation_runs_unico');
        });

        // "Qualsiasi stato" prima non partiva mai (bug): ora partirebbe a ogni cambio di stato.
        // Le automazioni esistenti così impostate vengono spente: l'agenzia le riaccende se le vuole.
        DB::table('automations')->where('trigger_type', 'status')->whereNull('tenant_status_id')->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::table('automations', fn (Blueprint $table) => $table->dropColumn(['subject', 'offset_days']));
        Schema::table('tenant_statuses', fn (Blueprint $table) => $table->dropColumn('notice_days'));
    }
};
