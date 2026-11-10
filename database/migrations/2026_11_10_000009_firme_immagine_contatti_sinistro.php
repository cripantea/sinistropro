<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Firme con immagine (logo, foto) e contatti della rubrica assegnati al sinistro per categoria.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_firme', function (Blueprint $table) {
            $table->string('immagine')->nullable()->after('testo');
            $table->string('immagine_mime', 50)->nullable()->after('immagine');
            $table->text('testo')->nullable()->change();
        });

        Schema::table('pratiche', function (Blueprint $table) {
            $table->json('contatti')->nullable()->after('tags'); // {"studio peritale": 12, …}
        });
    }

    public function down(): void
    {
        Schema::table('pratiche', fn (Blueprint $table) => $table->dropColumn('contatti'));
        Schema::table('email_firme', fn (Blueprint $table) => $table->dropColumn(['immagine', 'immagine_mime']));
    }
};
