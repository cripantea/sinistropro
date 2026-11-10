<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Firme email: personali (user_id) o di tutta l'agenzia (user_id vuoto); ognuno sceglie la sua predefinita.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_firme', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('nome', 80);
            $table->text('testo');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('firma_email_id')->nullable()->constrained('email_firme')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firma_email_id');
        });
        Schema::dropIfExists('email_firme');
    }
};
