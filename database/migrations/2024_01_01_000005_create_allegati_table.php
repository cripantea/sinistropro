<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allegati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pratica_id')->constrained('pratiche')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nome_file');
            $table->string('s3_key');
            $table->timestamps();

            $table->index(['pratica_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allegati');
    }
};
