<?php

use App\Models\{Cliente, EmailLog, Pratica, Tenant, TenantStatus, User};
use Inertia\Testing\AssertableInertia as Assert;

// Dashboard dell'agenzia: "Oggi lavora su" elenca i sinistri con l'avviso di oggi.

test('oggi lavora su: avvisati oggi e in scadenza, mai i chiusi né quelli di altri tenant', function () {
    $tenant = Tenant::create(['name' => 'Studio A']);
    $altro  = Tenant::create(['name' => 'Studio B']);
    $aperto = TenantStatus::create(['tenant_id' => $tenant->id, 'name' => 'Aperto', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $chiuso = TenantStatus::create(['tenant_id' => $tenant->id, 'name' => 'Chiuso', 'color' => '#111', 'order' => 1, 'is_closed' => true]);
    $statoB = TenantStatus::create(['tenant_id' => $altro->id, 'name' => 'Aperto', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant-admin']);
    $cliente = Cliente::create(['tenant_id' => $tenant->id, 'nome' => 'Mario Bianchi']);

    $nuova = fn (array $a) => Pratica::create([
        'tenant_id' => $tenant->id, 'utente_creatore_id' => $admin->id, 'cliente_id' => $cliente->id, 'current_status_id' => $aperto->id, ...$a,
    ]);

    $giaAvvisato = $nuova(['data_prossimo_avviso' => today()->addDays(30)]);   // l'avviso è partito: data già spostata
    $daAvvisare  = $nuova(['data_prossimo_avviso' => today()]);
    $nuova(['data_prossimo_avviso' => today(), 'current_status_id' => $chiuso->id]);
    $nuova(['data_prossimo_avviso' => today()->addDay()]);
    Pratica::create(['tenant_id' => $altro->id, 'utente_creatore_id' => $admin->id, 'current_status_id' => $statoB->id, 'data_prossimo_avviso' => today()]);

    EmailLog::create(['tenant_id' => $tenant->id, 'pratica_id' => $giaAvvisato->id, 'tipo' => 'avviso', 'status' => 'sent', 'to_address' => 'a@example.test']);

    $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('stats.todayReminders', 2)
        ->has('oggi', 2)
        ->where('oggi.0.id', $giaAvvisato->id)
        ->where('oggi.0.avviso', 'inviato')
        ->where('oggi.0.cliente', 'Mario Bianchi')
        ->where('oggi.1.id', $daAvvisare->id)
        ->where('oggi.1.avviso', 'in_arrivo'));
});

test("l'avviso delle 7 resta in lista tutto il giorno, anche dopo che la data è andata avanti", function () {
    $tenant = Tenant::create(['name' => 'Studio A']);
    $stato  = TenantStatus::create(['tenant_id' => $tenant->id, 'name' => 'Aperto', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $admin  = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant-admin']);
    $p = Pratica::create(['tenant_id' => $tenant->id, 'utente_creatore_id' => $admin->id, 'current_status_id' => $stato->id, 'data_prossimo_avviso' => today()->addDays(30)]);

    $this->travelTo(now('Europe/Rome')->setTime(7, 0)->utc());
    EmailLog::create(['tenant_id' => $tenant->id, 'pratica_id' => $p->id, 'tipo' => 'avviso', 'status' => 'sent', 'to_address' => 'a@example.test']);

    // In serata (23:30 ora italiana) il sinistro è ancora lì.
    $this->travelTo(now('Europe/Rome')->setTime(23, 30)->utc());
    $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('oggi', 1)->where('oggi.0.avviso', 'inviato'));

    // Il giorno dopo non c'è più.
    $this->travelTo(now('Europe/Rome')->addDay()->setTime(10, 0)->utc());
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('oggi', 0));
});
