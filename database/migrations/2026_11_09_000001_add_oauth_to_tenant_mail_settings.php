<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Caselle Microsoft (Outlook, Microsoft 365): niente password, accesso con OAuth e token rinnovati da soli.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_mail_settings', function (Blueprint $table) {
            $table->string('auth_type', 20)->default('password')->after('password');
            $table->text('oauth_refresh_token')->nullable()->after('auth_type');
            $table->text('oauth_access_token')->nullable()->after('oauth_refresh_token');
            $table->timestamp('oauth_expires_at')->nullable()->after('oauth_access_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_mail_settings', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'oauth_refresh_token', 'oauth_access_token', 'oauth_expires_at']);
        });
    }
};
