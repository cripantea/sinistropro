<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Corrispondenza email dentro il sinistro: le email scritte dal sinistro (e le risposte) restano legate a lui.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->foreignId('pratica_id')->nullable()->after('email_thread_id')->constrained('pratiche')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('pratica_id'));
    }
};
