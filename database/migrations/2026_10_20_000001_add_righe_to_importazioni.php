<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le righe del file caricato passano dal database invece che da un file su disco:
     * il sito (www-data) e il worker della coda (altro utente) le leggono entrambi.
     */
    public function up(): void
    {
        Schema::table('importazioni', function (Blueprint $table) {
            $table->longText('righe')->nullable()->after('file_id');
        });
    }

    public function down(): void
    {
        Schema::table('importazioni', function (Blueprint $table) {
            $table->dropColumn('righe');
        });
    }
};
