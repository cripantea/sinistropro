<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_templates', function (Blueprint $table) {
            // Percorso S3 del PDF "matrice" vuoto con campi AcroForm
            $table->string('pdf_template_s3_key')->nullable()->after('name');

            // Categoria documentale in cui verrà archiviato il PDF compilato
            $table->foreignId('output_document_category_id')
                  ->nullable()
                  ->after('pdf_template_s3_key')
                  ->constrained('document_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('module_templates', function (Blueprint $table) {
            $table->dropForeign(['output_document_category_id']);
            $table->dropColumn(['pdf_template_s3_key', 'output_document_category_id']);
        });
    }
};
