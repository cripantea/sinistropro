<?php

use App\Models\{Cliente, Contatto, Pratica, Tenant, TenantStatus, User, WhatsappSession};
use Illuminate\Support\Facades\Http;

// Regressioni dell'audit di sicurezza (ottobre 2026): isolamento tra tenant, revoca degli
// account, stati di altri tenant, rubrica agli esterni, webhook e collegamento WhatsApp.

beforeEach(function () {
    $this->tenantA = Tenant::create(['name' => 'Studio A']);
    $this->tenantB = Tenant::create(['name' => 'Studio B']);
    $this->statoA  = TenantStatus::create(['tenant_id' => $this->tenantA->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->statoB  = TenantStatus::create(['tenant_id' => $this->tenantB->id, 'name' => 'STATO-SEGRETO-B', 'color' => '#111', 'order' => 0, 'is_initial' => true, 'send_email_notification' => true]);

    $this->superadmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);
    $this->adminA     = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'tenant-admin']);
    $this->userA      = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'user']);
    $this->externalA  = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'external']);

    $this->clienteA = Cliente::create(['tenant_id' => $this->tenantA->id, 'nome' => 'Cliente A']);
    $this->clienteB = Cliente::create(['tenant_id' => $this->tenantB->id, 'nome' => 'CLIENTE-SEGRETO-B']);
    $this->praticaA = Pratica::create(['tenant_id' => $this->tenantA->id, 'utente_creatore_id' => $this->adminA->id, 'cliente_id' => $this->clienteA->id, 'current_status_id' => $this->statoA->id]);

    Contatto::create(['tenant_id' => $this->tenantA->id, 'tags' => ['perito'], 'nome' => 'Perito A', 'email' => 'perito@example.test', 'telefono' => '0000000000']);
});

test('eliminando un tenant i suoi utenti vengono disattivati e non vedono gli altri tenant', function () {
    $this->actingAs($this->superadmin)->delete("/superadmin/tenants/{$this->tenantA->id}")->assertRedirect();

    $orfano = $this->userA->fresh();
    expect($orfano->tenant_id)->toBeNull()->and($orfano->is_active)->toBeFalse();

    $this->post('/logout');
    $this->post('/login', ['email' => $orfano->email, 'password' => 'password']);
    $this->assertGuest();
});

test('un utente senza tenant (orfano di un vecchio tenant eliminato) non vede i dati di nessun tenant', function () {
    $orfano = User::factory()->create(['tenant_id' => null, 'role' => 'user', 'is_active' => true]);

    $this->actingAs($orfano)->get("/clienti/{$this->clienteB->id}/scheda")->assertNotFound();
    $this->actingAs($orfano)->get('/clienti/cerca?q=SEGRETO')->assertDontSee('CLIENTE-SEGRETO-B');

    $this->actingAs($orfano);
    expect(Cliente::count())->toBe(0)->and(Pratica::count())->toBe(0);
});

test('il superadmin senza tenant selezionato continua a vedere tutti i tenant', function () {
    $this->actingAs($this->superadmin);
    expect(Cliente::count())->toBe(2);
});

test('un tenant-admin senza tenant non può modificare un account superadmin', function () {
    $orfanoAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'tenant-admin']);

    $this->actingAs($orfanoAdmin)->patch("/team/{$this->superadmin->id}", [
        'name' => 'x', 'email' => 'attacker@example.test', 'password' => 'attackerpass1', 'role' => 'tenant-admin',
    ])->assertForbidden();
    $this->actingAs($orfanoAdmin)->patch("/team/{$this->superadmin->id}/toggle-active")->assertForbidden();

    expect($this->superadmin->fresh()->email)->not->toBe('attacker@example.test')
        ->and($this->superadmin->fresh()->role)->toBe('superadmin');
});

test('un utente disattivato perde subito la sessione già aperta', function () {
    $this->actingAs($this->userA)->get('/profile')->assertOk();

    $this->userA->update(['is_active' => false]);

    $this->get('/profile')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('ispezioni rifiuta lo stato di un altro tenant', function () {
    $this->actingAs($this->userA)->postJson("/pratiche/{$this->praticaA->id}/ispezioni", [
        'current_status_id' => $this->statoB->id, 'skip_confirmable_automations' => true,
    ])->assertSessionHasErrors('current_status_id');

    expect($this->praticaA->fresh()->current_status_id)->toBe($this->statoA->id);
});

test('l\'anteprima automazioni rifiuta lo stato di un altro tenant e non ne rivela il nome', function () {
    // Sulle rotte web gli errori di validazione tornano come redirect con errori in sessione.
    $res = $this->actingAs($this->userA)->postJson("/pratiche/{$this->praticaA->id}/automations/preview", [
        'tenant_status_id' => $this->statoB->id,
    ])->assertSessionHasErrors('tenant_status_id');

    expect($res->getContent())->not->toContain('STATO-SEGRETO-B');
});

test('l\'anteprima automazioni non restituisce la rubrica agli esterni', function () {
    $this->actingAs($this->externalA)->get('/rubrica')->assertForbidden();

    $this->actingAs($this->externalA)->postJson("/pratiche/{$this->praticaA->id}/automations/preview", [])
        ->assertOk()->assertJsonPath('rubrica', []);

    $this->actingAs($this->userA)->postJson("/pratiche/{$this->praticaA->id}/automations/preview", [])
        ->assertOk()->assertJsonPath('rubrica.0.email', 'perito@example.test');
});

test('la verifica del webhook rifiuta le richieste se il verify token non è configurato', function () {
    config(['services.whatsapp_cloud.verify_token' => null]);

    $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_challenge=%3Cscript%3Ex%3C%2Fscript%3E')->assertForbidden();
});

test('la verifica del webhook restituisce la challenge come testo semplice', function () {
    config(['services.whatsapp_cloud.verify_token' => 'segreto']);

    $res = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=segreto&hub_challenge=12345')->assertOk();

    expect($res->getContent())->toBe('12345')
        ->and($res->headers->get('Content-Type'))->toStartWith('text/plain');
});

test('il collegamento WhatsApp usa gli id letti da FusionWA, non quelli inviati dal browser', function () {
    Http::fake([
        '*/api/v1/widget/status*' => Http::response([
            'status' => 'CONNECTED', 'wabaId' => 'WABA-A', 'phoneNumberId' => 'PNID-A', 'phoneNumber' => '+39 000',
        ]),
        '*' => Http::response([]),
    ]);

    $this->actingAs($this->adminA)->postJson('/whatsapp/embedded-signup', [
        'waba_id' => 'WABA-ALTRUI', 'phone_number_id' => 'PNID-ALTRUI', 'phone_number' => '+39 999',
    ])->assertOk();

    $session = WhatsappSession::acrossAllTenants()->where('tenant_id', $this->tenantA->id)->first();
    expect($session->phone_number_id)->toBe('PNID-A')->and($session->waba_id)->toBe('WABA-A');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'widget/status')
        && str_contains($request->url(), 'customerId='.$this->tenantA->id));
});

test('il collegamento WhatsApp rifiuta un numero già collegato a un altro tenant', function () {
    WhatsappSession::create([
        'tenant_id' => $this->tenantB->id, 'phone_number_id' => 'PNID-B', 'waba_id' => 'WABA-B',
        'display_phone_number' => '+39 111', 'status' => 'active',
    ]);
    Http::fake([
        '*/api/v1/widget/status*' => Http::response([
            'status' => 'CONNECTED', 'wabaId' => 'WABA-B', 'phoneNumberId' => 'PNID-B', 'phoneNumber' => '+39 111',
        ]),
    ]);

    $this->actingAs($this->adminA)->postJson('/whatsapp/embedded-signup', [])->assertStatus(409);

    expect(WhatsappSession::acrossAllTenants()->where('tenant_id', $this->tenantA->id)->exists())->toBeFalse();
});

test('il collegamento WhatsApp non procede se FusionWA non risulta connesso', function () {
    Http::fake(['*/api/v1/widget/status*' => Http::response(['status' => 'PENDING'])]);

    $this->actingAs($this->adminA)->postJson('/whatsapp/embedded-signup', [
        'waba_id' => 'X', 'phone_number_id' => 'Y', 'phone_number' => 'Z',
    ])->assertStatus(422);
});
