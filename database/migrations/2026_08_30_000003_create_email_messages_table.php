<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('folder', ['inbox', 'sent']);
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->json('to_addresses');
            $table->json('cc_addresses')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->enum('status', ['received', 'sent', 'failed'])->default('received');
            $table->timestamp('email_timestamp');
            $table->timestamps();

            $table->unique(['tenant_id', 'message_id']);
            $table->index(['tenant_id', 'email_thread_id', 'email_timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
