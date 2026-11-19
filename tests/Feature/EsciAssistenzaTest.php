<?php

use App\Models\{Tenant, User};

test('esci dall\'assistenza riporta il superadmin al pannello, anche se premuto due volte', function () {
    $tenant = Tenant::create(['name' => 'Assiponte']);
    $super  = User::factory()->create(['role' => 'superadmin', 'tenant_id' => null]);
    $admin  = User::factory()->create(['role' => 'tenant-admin', 'tenant_id' => $tenant->id]);

    $this->actingAs($admin)->withSession(['impersonator_id' => $super->id])
        ->post('/impersonate/leave')->assertRedirect(route('superadmin.dashboard'));
    $this->assertAuthenticatedAs($super);

    // Secondo clic (o pagina vecchia in un'altra scheda): prima dava 403
    $this->post('/impersonate/leave')->assertRedirect(route('superadmin.dashboard'));
    $this->assertAuthenticatedAs($super);
});

test('un utente normale senza assistenza attiva torna alla sua dashboard', function () {
    $tenant = Tenant::create(['name' => 'Studio']);
    $user   = User::factory()->create(['role' => 'tenant-admin', 'tenant_id' => $tenant->id]);

    $this->actingAs($user)->post('/impersonate/leave')->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});
