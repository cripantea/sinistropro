<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE whatsapp_sessions SET status = 'connected' WHERE status NOT IN ('disconnected', 'starting', 'qr', 'connected')");
        DB::statement("ALTER TABLE whatsapp_sessions MODIFY status ENUM('disconnected', 'starting', 'qr', 'connected', 'pending', 'active', 'disabled') NOT NULL DEFAULT 'pending'");
        DB::statement("UPDATE whatsapp_sessions SET status = CASE WHEN status = 'connected' THEN 'active' ELSE 'pending' END");
        DB::statement("ALTER TABLE whatsapp_sessions MODIFY status ENUM('pending', 'active', 'disabled') NOT NULL DEFAULT 'pending'");

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('phone_number_id')->nullable()->unique()->after('tenant_id');
            $table->string('display_phone_number')->nullable()->after('phone_number_id');
            $table->dropColumn(['qr_code', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->text('qr_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->dropColumn(['phone_number_id', 'display_phone_number']);
        });

        DB::statement("ALTER TABLE whatsapp_sessions MODIFY status ENUM('disconnected', 'starting', 'qr', 'connected', 'pending', 'active', 'disabled') NOT NULL DEFAULT 'disconnected'");
        DB::statement("UPDATE whatsapp_sessions SET status = CASE WHEN status = 'active' THEN 'connected' ELSE 'disconnected' END");
        DB::statement("ALTER TABLE whatsapp_sessions MODIFY status ENUM('disconnected', 'starting', 'qr', 'connected') NOT NULL DEFAULT 'disconnected'");
    }
};
