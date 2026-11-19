<?php

use App\Models\{Cliente, ListaValori, Pratica, Tenant, TenantStatus, User};

beforeEach(function () {
    $this->tenant  = Tenant::create(['name' => 'Studio Test']);
    $stato         = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Aperto', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->user    = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $cliente       = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi']);
    // Sinistro "vecchio": aperto senza compagnia
    $this->pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $cliente->id, 'current_status_id' => $stato->id]);
    ListaValori::acrossAllTenants()->create(['tenant_id' => $this->tenant->id, 'slug' => 'compagnie', 'nome' => 'Compagnie', 'items' => ['Generali', 'Linear']]);
});

test('la scheda sinistro riceve la lista compagnie e la compagnia si aggiunge a un sinistro vecchio', function () {
    $this->actingAs($this->user)->get("/pratiche/{$this->pratica->id}")
        ->assertInertia(fn ($p) => $p->where('compagnie', ['Generali', 'Linear']));

    $this->actingAs($this->user)->patch("/pratiche/{$this->pratica->id}/compagnia", ['compagnia' => 'Linear'])->assertRedirect();
    expect($this->pratica->fresh()->compagnia)->toBe('Linear');

    // si può anche togliere
    $this->actingAs($this->user)->patch("/pratiche/{$this->pratica->id}/compagnia", ['compagnia' => null])->assertRedirect();
    expect($this->pratica->fresh()->compagnia)->toBeNull();
});

test('la compagnia si cambia anche da Modifica sinistro, e senza il campo resta com\'è', function () {
    $this->actingAs($this->user)->get("/pratiche/{$this->pratica->id}/edit")
        ->assertInertia(fn ($p) => $p->where('compagnie', ['Generali', 'Linear']));

    $this->actingAs($this->user)->put("/pratiche/{$this->pratica->id}", ['current_status_id' => $this->pratica->current_status_id, 'compagnia' => 'Generali'])->assertRedirect();
    expect($this->pratica->fresh()->compagnia)->toBe('Generali');

    $this->actingAs($this->user)->put("/pratiche/{$this->pratica->id}", ['current_status_id' => $this->pratica->current_status_id])->assertRedirect();
    expect($this->pratica->fresh()->compagnia)->toBe('Generali');
});

test('un utente esterno non può cambiare la compagnia', function () {
    $esterno = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'external']);
    $this->actingAs($esterno)->patch("/pratiche/{$this->pratica->id}/compagnia", ['compagnia' => 'Linear'])->assertForbidden();
    expect($this->pratica->fresh()->compagnia)->toBeNull();
});
