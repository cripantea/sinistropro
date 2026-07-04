<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#6B7280');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_statuses');
    }
};
