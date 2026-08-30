<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_mail_settings', function (Blueprint $table) {
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_encryption', 10)->nullable();
            $table->unsignedInteger('imap_last_uid_inbox')->nullable();
            $table->unsignedInteger('imap_last_uid_sent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_mail_settings', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host',
                'imap_port',
                'imap_encryption',
                'imap_last_uid_inbox',
                'imap_last_uid_sent',
            ]);
        });
    }
};
