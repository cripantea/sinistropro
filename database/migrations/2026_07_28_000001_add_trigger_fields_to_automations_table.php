<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->string('trigger_type', 20)->default('status')->after('tenant_status_id');
            $table->string('watched_field')->nullable()->after('trigger_type');
            $table->boolean('requires_confirmation')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn(['trigger_type', 'watched_field', 'requires_confirmation']);
        });
    }
};
