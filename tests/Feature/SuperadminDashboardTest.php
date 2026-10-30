<?php

use App\Models\{Tenant, User};
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Dashboard superadmin: incasso reale, obiettivo, uso e salute delle agenzie.

function attivita(User $u, ?User $impersonatore = null, string $quando = 'now'): void
{
    DB::table('audit_logs')->insert([
        'tenant_id' => $u->tenant_id, 'user_id' => $u->id, 'impersonated_by_user_id' => $impersonatore?->id,
        'action' => 'view', 'auditable_type' => 'pratica', 'auditable_id' => 1,
        'created_at' => now()->modify($quando), 'updated_at' => now(),
    ]);
}

test('incasso, utenti attivi e agenzie ferme contano solo i clienti veri', function () {
    $superadmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);

    $pagante = Tenant::create(['name' => 'Paga Stripe']);
    $pagante->forceFill(['billing_mode' => 'stripe', 'subscription_status' => 'active', 'subscription_interval' => 'year', 'stripe_subscription_id' => 'sub_1', 'price_yearly_cents' => 120000])->save();
    $esterno = Tenant::create(['name' => 'Fattura a parte']);
    $esterno->forceFill(['billing_mode' => 'esterno', 'price_monthly_cents' => 5000])->save();
    $fermo = Tenant::create(['name' => 'Fermo']);
    $fermo->forceFill(['billing_mode' => 'esterno'])->save();
    $demo = Tenant::create(['name' => 'Demo', 'settings' => ['demo' => true]]);

    $u1 = User::factory()->create(['tenant_id' => $pagante->id, 'role' => 'tenant-admin']);
    User::factory()->create(['tenant_id' => $pagante->id, 'role' => 'user']);
    $u3 = User::factory()->create(['tenant_id' => $fermo->id, 'role' => 'user']);
    $ud = User::factory()->create(['tenant_id' => $demo->id, 'role' => 'tenant-admin']);

    attivita($u1);
    attivita($u3, $superadmin);          // il nostro "Accedi come" non conta
    attivita($u3, null, '-20 days');     // l'ultima attività vera è di 20 giorni fa
    attivita($ud);
    attivita($ud, null, '-3 days');      // due giorni di demo

    $this->actingAs($superadmin)->post('/superadmin/obiettivo', ['euro' => 2000])->assertRedirect();

    $this->get('/superadmin')->assertInertia(fn (Assert $page) => $page
        ->component('Superadmin/Dashboard')
        ->where('incasso_mensile_cents', 10000 + 5000)   // 1200/anno = 100/mese + 50 fatturati a parte
        ->where('obiettivo_cents', 200000)
        ->where('agenzie_paganti', 2)
        ->where('agenzie_totali', 3)                     // il demo resta fuori
        ->where('utenti_attivi_7', 1)
        ->where('demo_30', 2)
        ->where('inattive', 2)                           // "Fattura a parte" mai usata + "Fermo"
        ->where('agenzie.1.nome', 'Fermo')->where('agenzie.1.prezzo_mancante', true)  // meno sane in cima; "Fermo" non ha prezzo
        ->where('agenzie.2.nome', 'Paga Stripe'));
});
