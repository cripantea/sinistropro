<?php

use App\Models\{Cliente, ListaValori, Tenant, TenantStatus, User};

// Registrazione da soli: crea agenzia (Stripe, sola lettura finché non paga) + amministratore, entra e va a pagare.

test('ci si registra con nome, agenzia, email e password e si arriva al pagamento', function () {
    $altra = Tenant::create(['name' => 'Malacrida']);

    $this->get('/registrati')->assertOk();

    $this->post('/registrati', [
        'nome' => 'Mario Rossi', 'agenzia' => 'Agenzia Rossi', 'email' => 'mario@agenzia.it', 'password' => 'password123',
    ])->assertRedirect(route('abbonamento'));

    $user = User::where('email', 'mario@agenzia.it')->first();
    $tenant = $user->tenant;
    expect($user->role)->toBe('tenant-admin')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($tenant->name)->toBe('Agenzia Rossi')
        ->and($tenant->billing_mode)->toBe('stripe')
        ->and($tenant->hasAccess())->toBeFalse()
        ->and($tenant->initialStatus()?->name)->toBe('Aperto')
        ->and(TenantStatus::where('tenant_id', $tenant->id)->where('is_closed', true)->count())->toBe(1)
        ->and(ListaValori::forTenant($tenant->id)->where('slug', 'compagnie')->exists())->toBeTrue();
    $this->assertAuthenticatedAs($user);

    // Prima di pagare: vede le pagine ma non può creare niente.
    $this->get('/abbonamento')->assertOk();
    $this->get('/dashboard')->assertOk();
    $this->post('/clienti', ['nome' => 'TEST'])->assertSessionHas('error');
    expect(Cliente::forTenant($tenant->id)->count())->toBe(0);

    // Dopo il pagamento si sblocca.
    $tenant->forceFill(['subscription_status' => 'active', 'stripe_subscription_id' => 'sub_x'])->save();
    expect($tenant->fresh()->hasAccess())->toBeTrue();

    // L'altra agenzia non è stata toccata.
    expect(TenantStatus::where('tenant_id', $altra->id)->count())->toBe(0)
        ->and($altra->fresh()->billing_mode)->toBe('esterno');
});

test('email già usata, password corta e bot vengono rifiutati', function () {
    User::factory()->create(['email' => 'mario@agenzia.it']);

    $this->post('/registrati', ['nome' => 'M', 'agenzia' => 'A', 'email' => 'mario@agenzia.it', 'password' => 'password123'])
        ->assertSessionHasErrors('email');
    $this->post('/registrati', ['nome' => 'M', 'agenzia' => 'A', 'email' => 'altro@agenzia.it', 'password' => 'corta'])
        ->assertSessionHasErrors('password');
    $this->post('/registrati', ['nome' => 'M', 'agenzia' => 'A', 'email' => 'bot@agenzia.it', 'password' => 'password123', 'sito' => 'x'])
        ->assertStatus(422);

    expect(Tenant::count())->toBe(0);
    $this->assertGuest();
});
