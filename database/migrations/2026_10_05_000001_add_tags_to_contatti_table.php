<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Da "tipo" fisso (perito | carrozzeria) a rubrica con tag liberi: un contatto può avere
        // più tag e se ne possono creare di nuovi. Il vecchio tipo diventa il primo tag.
        Schema::table('contatti', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('nome');
            $table->string('tipo', 20)->nullable()->change();
        });

        DB::table('contatti')->whereNotNull('tipo')->orderBy('id')->each(function ($c): void {
            DB::table('contatti')->where('id', $c->id)->update(['tags' => json_encode([$c->tipo])]);
        });
    }

    public function down(): void
    {
        // tipo = primo tag, così il rollback non perde l'informazione principale.
        DB::table('contatti')->orderBy('id')->each(function ($c): void {
            $tags = json_decode($c->tags ?? '[]', true) ?: [];
            DB::table('contatti')->where('id', $c->id)->update(['tipo' => in_array('carrozzeria', $tags, true) ? 'carrozzeria' : 'perito']);
        });

        Schema::table('contatti', function (Blueprint $table) {
            $table->dropColumn('tags');
            $table->string('tipo', 20)->nullable(false)->change();
        });
    }
};
