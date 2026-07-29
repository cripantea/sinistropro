<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            // Array di destinatari TO: [{ type: 'cliente' } | { type: 'user', user_id: 5 }]
            $table->json('recipients_to')->nullable()->after('recipient');
            // Array di destinatari CC: [{ type: 'user', user_id: 3 }]
            $table->json('recipients_cc')->nullable()->after('recipients_to');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn(['recipients_to', 'recipients_cc']);
        });
    }
};
