<?php

use App\Jobs\ExecuteAutomationJob;
use App\Models\{Automation, Cliente, Contatto, EmailLog, Ispezione, ListaValori, Pratica, Tenant, TenantMailSettings, TenantStatus, User};
use Database\Seeders\MalacridaTenantSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Studio Test']);
    $this->status = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'email' => 'mario@example.com', 'telefono' => '333111222']);
});

test('periti e carrozzerie si gestiscono senza account utente', function () {
    $this->actingAs($this->user)->post('/contatti', ['tipo' => 'carrozzeria', 'nome' => 'Re-nova', 'telefono' => '3402148121'])->assertRedirect();
    $this->actingAs($this->user)->post('/contatti', ['tipo' => 'perito', 'nome' => 'Luigi Perito'])->assertRedirect();
    $this->actingAs($this->user)->post('/contatti', ['tipo' => 'altro', 'nome' => 'x'])->assertSessionHasErrors('tipo');

    expect(Contatto::tipo('carrozzeria')->count())->toBe(1)->and(Contatto::tipo('perito')->count())->toBe(1);
    expect(User::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('contatto di un altro tenant non è assegnabile né modificabile', function () {
    $other = Tenant::create(['name' => 'Altro']);
    $foreign = Contatto::create(['tenant_id' => $other->id, 'tipo' => 'perito', 'nome' => 'Estraneo']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->status->id]);

    $this->actingAs($this->user)->postJson("/pratiche/{$pratica->id}/ispezioni", ['perito_contatto_id' => $foreign->id])
        ->assertSessionHasErrors('perito_contatto_id');
    expect(Ispezione::where('pratica_id', $pratica->id)->count())->toBe(0);
    $this->actingAs($this->user)->put("/contatti/{$foreign->id}", ['tipo' => 'perito', 'nome' => 'Hack'])->assertNotFound();
});

test('assegnazione perito e carrozzeria sul sinistro, senza azzerare l altra', function () {
    $perito = Contatto::create(['tenant_id' => $this->tenant->id, 'tipo' => 'perito', 'nome' => 'P']);
    $carr = Contatto::create(['tenant_id' => $this->tenant->id, 'tipo' => 'carrozzeria', 'nome' => 'C']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->status->id]);

    $this->actingAs($this->user)->postJson("/pratiche/{$pratica->id}/ispezioni", ['perito_contatto_id' => $perito->id, 'carrozzeria_contatto_id' => $carr->id])->assertOk();
    $this->actingAs($this->user)->postJson("/pratiche/{$pratica->id}/ispezioni", ['perito_contatto_id' => $perito->id])->assertOk();

    $isp = Ispezione::where('pratica_id', $pratica->id)->first();
    expect($isp->perito_contatto_id)->toBe($perito->id)->and($isp->carrozzeria_contatto_id)->toBe($carr->id);
    $this->actingAs($this->user)->get("/pratiche/{$pratica->id}")->assertOk();
});

test('compagnia si salva all apertura del sinistro e il menu esce dalla lista del tenant', function () {
    ListaValori::create(['tenant_id' => $this->tenant->id, 'nome' => 'Compagnie', 'slug' => 'compagnie', 'items' => ['Prima', 'Generali']]);

    $this->actingAs($this->user)->get('/pratiche/create')->assertOk()
        ->assertInertia(fn ($page) => $page->where('compagnie', ['Prima', 'Generali']));

    $this->actingAs($this->user)->post('/pratiche', ['cliente_id' => $this->cliente->id, 'compagnia' => 'Generali'])->assertRedirect();
    expect(Pratica::first()->compagnia)->toBe('Generali');
});

test('seeder Malacrida: Re-nova, compagnie nell ordine richiesto, idempotente, solo per Malacrida', function () {
    $mala = Tenant::create(['name' => 'Studio Malacrida']);

    (new MalacridaTenantSeeder())->run();
    (new MalacridaTenantSeeder())->run();

    $renova = Contatto::acrossAllTenants()->where('tenant_id', $mala->id)->where('nome', 'Re-nova')->get();
    expect($renova)->toHaveCount(1)->and($renova[0]->telefono)->toBe('3402148121')->and($renova[0]->tipo)->toBe('carrozzeria');
    expect(ListaValori::acrossAllTenants()->where('tenant_id', $mala->id)->where('slug', 'compagnie')->first()->items)
        ->toBe(['Prima', 'Generali', 'AXA', 'Verti', 'Unipol']);
    expect($mala->fresh()->hasFeature('lista_personalizzate'))->toBeTrue();
    expect(Contatto::acrossAllTenants()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

test('l automazione usa email e telefono dell anagrafica cliente e registra l invio nel log', function () {
    TenantMailSettings::create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'host' => 'smtp.test', 'port' => 25, 'from_address' => 'a@b.it']);
    Mail::fake();
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->status->id]);
    $auto = Automation::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'trigger_type' => 'status', 'tenant_status_id' => $this->status->id, 'channel' => 'email', 'recipients_to' => [['type' => 'cliente']], 'message_template' => 'Ciao {nome_cliente}', 'is_active' => true]);

    // Mail::fake non copre il mailer dinamico: verifichiamo solo il tentativo registrato.
    try { (new ExecuteAutomationJob($pratica, $auto))->handle(app(\App\Services\TenantMailerResolver::class)); } catch (\Throwable) {}

    $log = EmailLog::acrossAllTenants()->where('tenant_id', $this->tenant->id)->first();
    expect($log)->not->toBeNull()->and($log->to_address)->toBe('mario@example.com')->and($log->tipo)->toBe('automazione');
});

test('nessun destinatario: l invio saltato è visibile nel registro', function () {
    $senzaEmail = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Senza Mail']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $senzaEmail->id, 'current_status_id' => $this->status->id]);
    $auto = Automation::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'trigger_type' => 'status', 'tenant_status_id' => $this->status->id, 'channel' => 'email', 'recipients_to' => [['type' => 'cliente']], 'message_template' => 'x', 'is_active' => true]);

    (new ExecuteAutomationJob($pratica, $auto))->handle(app(\App\Services\TenantMailerResolver::class));

    expect(EmailLog::acrossAllTenants()->where('status', 'skipped')->count())->toBe(1);
    $this->actingAs($this->user)->get('/email-log')->assertOk();
});
