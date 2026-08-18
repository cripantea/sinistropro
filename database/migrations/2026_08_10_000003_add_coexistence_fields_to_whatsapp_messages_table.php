<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->enum('source', ['api', 'echo', 'history'])->default('api')->after('direction');
            $table->timestamp('wa_timestamp')->nullable()->after('wa_message_id');
        });

        // Righe già esistenti: usa created_at come miglior approssimazione,
        // altrimenti l'ordinamento per wa_timestamp le metterebbe prima di tutto (NULL first).
        DB::statement('UPDATE whatsapp_messages SET wa_timestamp = created_at WHERE wa_timestamp IS NULL');
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['source', 'wa_timestamp']);
        });
    }
};
