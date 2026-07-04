<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allegati', function (Blueprint $table) {
            $table->foreignId('document_category_id')
                ->nullable()
                ->after('nome_file')
                ->constrained('document_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('allegati', function (Blueprint $table) {
            $table->dropForeign(['document_category_id']);
            $table->dropColumn('document_category_id');
        });
    }
};
