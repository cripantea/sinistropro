<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allegati', function (Blueprint $table) {
            $table->enum('source', ['generato', 'caricato'])->default('caricato')->after('document_category_id');
            $table->foreignId('module_template_id')
                  ->nullable()
                  ->after('source')
                  ->constrained('module_templates')
                  ->nullOnDelete();
        });

        // I moduli generati da PdfFormFillerService salvano sempre il PDF sotto un path
        // che contiene il segmento "/moduli/", a differenza degli upload manuali.
        DB::table('allegati')->where('s3_key', 'like', '%/moduli/%')->update(['source' => 'generato']);
    }

    public function down(): void
    {
        Schema::table('allegati', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_template_id');
            $table->dropColumn('source');
        });
    }
};
