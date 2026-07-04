<?php

use App\Models\DocumentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Default categories for insurance/claims workflows
        $defaults = [
            ['name' => 'Denuncia e Sinistro',      'description' => 'Atti di denuncia, polizze, documenti relativi al sinistro'],
            ['name' => 'Perizie e Valutazioni',     'description' => 'Perizie tecniche, stime di danno, relazioni di ispezione'],
            ['name' => 'Foto e Media',              'description' => 'Fotografie, video, documentazione visiva dei danni'],
            ['name' => 'Contabilità e Fatture',     'description' => 'Fatture, preventivi, ricevute, documentazione contabile'],
            ['name' => 'Corrispondenza ed Email',   'description' => 'Lettere, email, comunicazioni ufficiali'],
        ];

        foreach ($defaults as $cat) {
            DocumentCategory::create($cat);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
