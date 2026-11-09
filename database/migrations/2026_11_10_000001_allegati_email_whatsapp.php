<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Allegati visibili: immagini dentro il testo delle email (cid) e nome dei documenti WhatsApp.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_attachments', function (Blueprint $table) {
            $table->string('content_id')->nullable()->after('s3_key');
            $table->boolean('is_inline')->default(false)->after('content_id');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('media_filename')->nullable()->after('media_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('email_attachments', fn (Blueprint $table) => $table->dropColumn(['content_id', 'is_inline']));
        Schema::table('whatsapp_messages', fn (Blueprint $table) => $table->dropColumn('media_filename'));
    }
};
