<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Etichette libere sui sinistri (es. "urgente", "legale", "RCA"), per riconoscerli e filtrarli.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pratiche', fn (Blueprint $table) => $table->json('tags')->nullable()->after('custom_fields'));
    }

    public function down(): void
    {
        Schema::table('pratiche', fn (Blueprint $table) => $table->dropColumn('tags'));
    }
};
