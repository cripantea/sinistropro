<?php

use App\Mail\TenantMailTestMail;
use App\Models\{Cliente, EmailLog, Pratica, Tenant, TenantStatus, User, Veicolo};
use App\Services\TenantMailerResolver;

// demo:popola: dati finti solo nel tenant indicato (id + nome), solo se vuoto; gli altri tenant mai toccati.

beforeEach(function () {
    $this->altro = Tenant::create(['name' => 'Malacrida']);
    $this->altroStato = TenantStatus::create(['tenant_id' => $this->altro->id, 'name' => 'Aperta', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->altroCliente = Cliente::create(['tenant_id' => $this->altro->id, 'nome' => 'CLIENTE VERO']);

    $this->demo = Tenant::create(['name' => 'demo', 'settings' => []]);
    $this->admin = User::factory()->create(['tenant_id' => $this->demo->id, 'role' => 'tenant-admin']);
});

test('crea clienti, veicoli e sinistri solo nel tenant demo', function () {
    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo'])->assertSuccessful();

    expect(Cliente::forTenant($this->demo->id)->count())->toBe(15)
        ->and(Pratica::forTenant($this->demo->id)->count())->toBe(18)
        ->and(Pratica::forTenant($this->demo->id)->whereNull('veicolo_id')->count())->toBeGreaterThan(0)
        ->and(Veicolo::forTenant($this->demo->id)->count())->toBeGreaterThan(0)
        ->and(TenantStatus::where('tenant_id', $this->demo->id)->where('is_initial', true)->count())->toBe(1);

    // Ogni sinistro punta a clienti, veicoli e stati del tenant demo.
    Pratica::forTenant($this->demo->id)->with(['cliente', 'veicolo', 'currentStatus'])->get()->each(function ($p) {
        expect($p->cliente->tenant_id)->toBe($this->demo->id)
            ->and($p->currentStatus->tenant_id)->toBe($this->demo->id);
        if ($p->veicolo) {
            expect($p->veicolo->tenant_id)->toBe($this->demo->id)->and($p->veicolo->cliente_id)->toBe($p->cliente_id);
        }
    });

    // L'altro tenant è com'era.
    expect(Cliente::forTenant($this->altro->id)->pluck('nome')->all())->toBe(['CLIENTE VERO'])
        ->and(Pratica::forTenant($this->altro->id)->count())->toBe(0)
        ->and(TenantStatus::where('tenant_id', $this->altro->id)->pluck('id')->all())->toBe([$this->altroStato->id])
        ->and($this->altro->fresh()->settings)->toBe($this->altro->settings);

    $demo = $this->demo->fresh();
    expect($demo->getSetting('demo'))->toBeTrue()->and($demo->hasFeature('clienti'))->toBeTrue();

    // La scheda e la board si aprono.
    $this->actingAs($this->admin)->get('/pratiche/kanban')->assertOk();
    $this->get('/dashboard')->assertOk();
    $this->get('/clienti')->assertOk();
    $this->get('/pratiche/' . Pratica::forTenant($this->demo->id)->first()->id)->assertOk();
});

test('si rifiuta con nome sbagliato o tenant non vuoto', function () {
    $this->artisan('demo:popola', ['tenant' => $this->altro->id, '--nome' => 'demo'])->assertFailed();
    $this->artisan('demo:popola', ['tenant' => $this->demo->id])->assertFailed();
    // Tenant con dati veri: nessuna modifica.
    $this->artisan('demo:popola', ['tenant' => $this->altro->id, '--nome' => 'Malacrida'])->assertFailed();
    expect(Cliente::forTenant($this->altro->id)->count())->toBe(1)->and($this->altro->fresh()->getSetting('demo'))->toBeNull();

    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo'])->assertSuccessful();
    // Seconda volta: il tenant non è più vuoto.
    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo'])->assertFailed();
    expect(Pratica::forTenant($this->demo->id)->count())->toBe(18);
});

test('dall\'account demo non parte nessuna email', function () {
    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo'])->assertSuccessful();

    app(TenantMailerResolver::class)->send($this->demo->fresh(), 'qualcuno@example.com', new TenantMailTestMail($this->demo), [], ['tipo' => 'avviso']);

    expect(EmailLog::acrossAllTenants()->where('tenant_id', $this->demo->id)->value('status'))->toBe('skipped');
});
