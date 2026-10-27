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

test('le automazioni demo chiedono conferma al cambio stato e non mandano email vere', function () {
    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo'])->assertSuccessful();
    expect(\App\Models\Automation::where('tenant_id', $this->demo->id)->where('requires_confirmation', true)->count())->toBe(4);

    $perizia = TenantStatus::where('tenant_id', $this->demo->id)->where('name', 'In perizia')->first();
    $pratica = Pratica::forTenant($this->demo->id)->whereHas('currentStatus', fn ($q) => $q->where('name', 'Aperto'))->first();

    $res = $this->actingAs($this->admin)->postJson("/pratiche/{$pratica->id}/automations/preview", ['tenant_status_id' => $perizia->id])->assertOk();
    expect($res->json('automations.0.name'))->toBe('Avviso perizia a cliente e perito')
        ->and($res->json('automations.0.message'))->toContain((string) $pratica->id);

    $this->patchJson("/pratiche/{$pratica->id}/status", ['current_status_id' => $perizia->id])->assertOk();
    expect(EmailLog::acrossAllTenants()->where('tenant_id', $this->demo->id)->where('status', 'sent')->count())->toBe(0);
});

test('--automazioni le aggiunge a un demo già popolato, mai a un tenant vero', function () {
    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo'])->assertSuccessful();
    \App\Models\Automation::where('tenant_id', $this->demo->id)->delete();

    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo', '--automazioni' => true])->assertSuccessful();
    expect(\App\Models\Automation::where('tenant_id', $this->demo->id)->count())->toBe(4);
    // Seconda volta: ci sono già.
    $this->artisan('demo:popola', ['tenant' => $this->demo->id, '--nome' => 'demo', '--automazioni' => true])->assertFailed();

    TenantStatus::create(['tenant_id' => $this->altro->id, 'name' => 'Due', 'color' => '#000', 'order' => 1]);
    TenantStatus::create(['tenant_id' => $this->altro->id, 'name' => 'Chiusa', 'color' => '#000', 'order' => 2, 'is_closed' => true]);
    $this->artisan('demo:popola', ['tenant' => $this->altro->id, '--nome' => 'Malacrida', '--automazioni' => true])->assertFailed();
    expect(\App\Models\Automation::where('tenant_id', $this->altro->id)->count())->toBe(0);
});
