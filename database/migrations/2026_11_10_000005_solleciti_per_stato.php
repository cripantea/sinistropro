<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Solleciti "X giorni fermo in uno stato": serve sapere da quando il sinistro è nello stato attuale.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pratiche', fn (Blueprint $table) => $table->timestamp('status_changed_at')->nullable()->after('current_status_id'));
        Schema::table('automations', fn (Blueprint $table) => $table->boolean('ripeti')->default(false)->after('offset_days'));

        // Per i sinistri esistenti il conto parte da oggi: nessuna raffica di solleciti al rilascio.
        DB::table('pratiche')->whereNull('status_changed_at')->update(['status_changed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('automations', fn (Blueprint $table) => $table->dropColumn('ripeti'));
        Schema::table('pratiche', fn (Blueprint $table) => $table->dropColumn('status_changed_at'));
    }
};
