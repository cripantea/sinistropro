<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pratica_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pratica_id')
                  ->constrained('pratiche')
                  ->cascadeOnDelete();
            $table->foreignId('module_template_id')
                  ->constrained('module_templates')
                  ->cascadeOnDelete();
            $table->json('values');
            $table->timestamps();

            $table->index('pratica_id');
            $table->index('module_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pratica_modules');
    }
};
