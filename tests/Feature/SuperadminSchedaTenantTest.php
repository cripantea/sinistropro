<?php

use App\Models\{Tenant, TenantStatus, User};
use Inertia\Testing\AssertableInertia as Assert;

// Scheda del tenant nel superadmin: utenti dentro il tenant e salvataggi che restano nella sezione aperta.

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Studio A']);
    $this->altro  = Tenant::create(['name' => 'Studio B']);
    TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);

    $this->superadmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);
    $this->admin      = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin', 'name' => 'Admin A']);
    $this->spento     = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user', 'is_active' => false, 'name' => 'Spento A']);
    User::factory()->create(['tenant_id' => $this->altro->id, 'role' => 'user', 'name' => 'Utente B']);
});

test('la scheda del tenant mostra tutti i suoi utenti, anche disabilitati, e solo i suoi', function () {
    $this->actingAs($this->superadmin)
        ->get("/superadmin/tenants/{$this->tenant->id}/edit?tab=utenti")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Superadmin/Tenants/Edit')
            ->has('utenti', 2)
            ->where('utenti.0.name', 'Admin A')
            ->where('utenti.1.name', 'Spento A')
            ->where('utenti.1.is_active', false)
            ->where('praticheCount', 0));
});

test('salvare il tenant o un utente riporta alla sezione da cui si è partiti', function () {
    $sezione = "/superadmin/tenants/{$this->tenant->id}/edit?tab=generale";

    $this->actingAs($this->superadmin)->from($sezione)
        ->put("/superadmin/tenants/{$this->tenant->id}", [
            'name' => 'Studio A2',
            'default_notice_days' => 30,
            'statuses' => [['name' => 'Nuova', 'color' => '#000000', 'is_closed' => false, 'is_initial' => true]],
        ])
        ->assertRedirect($sezione);

    $utenti = "/superadmin/tenants/{$this->tenant->id}/edit?tab=utenti";
    $this->from($utenti)
        ->post('/superadmin/users', ['name' => 'Nuovo', 'email' => 'nuovo@example.test', 'password' => 'password123', 'role' => 'user', 'tenant_id' => $this->tenant->id])
        ->assertRedirect($utenti);

    expect($this->tenant->fresh()->name)->toBe('Studio A2')
        ->and(User::where('email', 'nuovo@example.test')->value('tenant_id'))->toBe($this->tenant->id);
});

test('una categoria nuova creata dalla scheda del tenant è accesa solo lì e spenta per le altre agenzie', function () {
    $this->actingAs($this->superadmin)
        ->from("/superadmin/tenants/{$this->tenant->id}/edit?tab=categories")
        ->post('/superadmin/document-categories', ['name' => 'Polizze', 'tenant_id' => (string) $this->tenant->id])
        ->assertRedirect("/superadmin/tenants/{$this->tenant->id}/edit?tab=categories");

    $categoria = \App\Models\DocumentCategory::where('name', 'Polizze')->firstOrFail();
    $accesa = fn (Tenant $t) => (bool) $categoria->tenants()->whereKey($t->id)->first()?->pivot->is_enabled;

    expect($accesa($this->tenant))->toBeTrue()
        ->and($accesa($this->altro))->toBeFalse();

    // L'altra agenzia non la vede nemmeno nella propria scheda.
    $this->get("/superadmin/tenants/{$this->altro->id}/edit")
        ->assertInertia(fn (Assert $page) => $page->where(
            'categoriesConfig',
            fn ($cats) => collect($cats)->firstWhere('name', 'Polizze')['is_enabled'] === false
        ));
});
