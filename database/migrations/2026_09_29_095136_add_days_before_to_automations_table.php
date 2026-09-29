<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            // Quanti giorni PRIMA della data scatenare l'automazione (0 = il giorno stesso).
            // Usato solo per trigger_type = 'cliente_date_field'.
            $table->unsignedSmallInteger('days_before')->default(0)->after('watched_field');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('days_before');
        });
    }
};
