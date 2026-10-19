<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Chi guidava o segue il sinistro quando non è il proprietario (auto aziendali, leasing, noleggio). */
    public function up(): void
    {
        Schema::table('pratiche', function (Blueprint $table) {
            $table->string('conducente_nome')->nullable()->after('veicolo_id');
            $table->string('conducente_telefono', 50)->nullable()->after('conducente_nome');
        });
    }

    public function down(): void
    {
        Schema::table('pratiche', function (Blueprint $table) {
            $table->dropColumn(['conducente_nome', 'conducente_telefono']);
        });
    }
};
