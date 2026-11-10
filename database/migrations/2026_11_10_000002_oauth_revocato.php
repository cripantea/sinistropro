<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Quando Microsoft/Google revocano il permesso (password cambiata, accesso tolto) la casella va ricollegata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_mail_settings', function (Blueprint $table) {
            $table->timestamp('oauth_revocato_at')->nullable()->after('oauth_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_mail_settings', fn (Blueprint $table) => $table->dropColumn('oauth_revocato_at'));
    }
};
