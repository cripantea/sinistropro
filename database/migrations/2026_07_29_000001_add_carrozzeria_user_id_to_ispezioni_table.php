<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ispezioni', function (Blueprint $table) {
            $table->foreignId('carrozzeria_user_id')->nullable()->after('assegnato_a_user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ispezioni', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'carrozzeria_user_id');
            $table->dropColumn('carrozzeria_user_id');
        });
    }
};
