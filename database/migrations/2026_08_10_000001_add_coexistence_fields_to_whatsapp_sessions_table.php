<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('waba_id')->nullable()->after('phone_number_id');
            $table->string('business_id')->nullable()->after('waba_id');
            $table->text('access_token')->nullable()->after('business_id');
            $table->boolean('is_on_biz_app')->default(false)->after('access_token');
            $table->string('platform_type')->nullable()->after('is_on_biz_app');
            $table->foreignId('connected_by_user_id')->nullable()->after('platform_type')
                  ->constrained('users')->nullOnDelete();
            $table->string('history_sync_status')->nullable()->after('connected_by_user_id');
            $table->string('disconnection_reason')->nullable()->after('history_sync_status');
            $table->timestamp('disconnected_at')->nullable()->after('disconnection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connected_by_user_id');
            $table->dropColumn([
                'waba_id',
                'business_id',
                'access_token',
                'is_on_biz_app',
                'platform_type',
                'history_sync_status',
                'disconnection_reason',
                'disconnected_at',
            ]);
        });
    }
};
