<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_document_categories', function (Blueprint $table) {
            $table->foreignId('automation_id')
                  ->constrained('automations')
                  ->cascadeOnDelete();
            $table->foreignId('document_category_id')
                  ->constrained('document_categories')
                  ->cascadeOnDelete();

            $table->primary(['automation_id', 'document_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_document_categories');
    }
};
