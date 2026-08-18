<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `string` invece di un secondo ENUM: la sintassi MODIFY ... ENUM(...) usata
        // in precedenza è MySQL-specifica e rompe la suite di test (SQLite in-memory).
        // Nessun impatto sui DB già migrati: le migration già eseguite non vengono ripetute.
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        DB::statement("UPDATE whatsapp_sessions SET status = CASE WHEN status = 'connected' THEN 'active' ELSE 'pending' END");

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

        DB::statement("UPDATE whatsapp_sessions SET status = CASE WHEN status = 'active' THEN 'connected' ELSE 'disconnected' END");

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->enum('status', ['disconnected', 'starting', 'qr', 'connected'])->default('disconnected')->change();
        });
    }
};
