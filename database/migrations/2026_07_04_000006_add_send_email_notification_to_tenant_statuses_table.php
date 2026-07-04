<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_statuses', function (Blueprint $table) {
            $table->boolean('send_email_notification')->default(false)->after('responsible_role');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_statuses', function (Blueprint $table) {
            $table->dropColumn('send_email_notification');
        });
    }
};
