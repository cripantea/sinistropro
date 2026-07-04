<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_statuses', function (Blueprint $table) {
            $table->boolean('is_closed')->default(false)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_statuses', function (Blueprint $table) {
            $table->dropColumn('is_closed');
        });
    }
};
