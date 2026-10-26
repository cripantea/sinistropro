<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 'esterno' = fatturato a parte (Malacrida, Unipol, demo): accesso sempre pieno, Stripe mai toccato.
            // Tutti i tenant già esistenti restano 'esterno'; quelli creati dal superadmin o dalla registrazione nascono 'stripe'.
            $table->string('billing_mode', 20)->default('esterno');
            // Prezzi personalizzati in centesimi, IVA inclusa; null = prezzo standard (config/billing.php).
            $table->unsignedInteger('price_monthly_cents')->nullable();
            $table->unsignedInteger('price_yearly_cents')->nullable();
            $table->unsignedInteger('setup_fee_cents')->nullable();
            $table->timestamp('setup_fee_paid_at')->nullable();
            // Accesso pieno regalato fino a questa data (compresa), anche senza abbonamento.
            $table->date('free_until')->nullable();

            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('subscription_status', 30)->nullable();
            $table->string('subscription_interval', 10)->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->boolean('subscription_cancel_at_period_end')->default(false);
            $table->json('billing_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropColumn([
                'billing_mode', 'price_monthly_cents', 'price_yearly_cents', 'setup_fee_cents', 'setup_fee_paid_at',
                'free_until', 'stripe_customer_id', 'stripe_subscription_id', 'subscription_status',
                'subscription_interval', 'subscription_ends_at', 'subscription_cancel_at_period_end', 'billing_details',
            ]);
        });
    }
};
