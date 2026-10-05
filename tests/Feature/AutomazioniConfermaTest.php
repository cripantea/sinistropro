<?php

use App\Jobs\ExecuteAutomationJob;
use App\Models\{Automation, Cliente, Contatto, EmailLog, Ispezione, Pratica, Tenant, TenantStatus, User};
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Solo la consegna SMTP è sostituita: destinatari, anteprima e override sono codice reale.
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void
        {
            EmailLog::registra(array_merge($logContext, [
                'tenant_id' => $tenant->id, 'to_address' => $to, 'cc_addresses' => $cc ?: null, 'status' => 'sent',
                'subject' => $mailable->envelope()->subject ?? null,
            ]));
        }
    });

    $this->tenant   = Tenant::create(['name' => 'Studio Test']);
    $this->nuova    = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->inviata  = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Inviata al perito', 'color' => '#111', 'order' => 1]);
    $this->user     = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->cliente  = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'email' => 'mario@example.com', 'telefono' => '333111222']);
    $this->pratica  = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->nuova->id]);
    $this->auto     = Automation::create([
        'tenant_id' => $this->tenant->id, 'name' => 'Avvisa cliente e perito', 'trigger_type' => 'status', 'tenant_status_id' => $this->inviata->id,
        'channel' => 'email', 'recipients_to' => [['type' => 'cliente'], ['type' => 'perito']],
        'message_template' => 'Ciao {nome_cliente}, la pratica {numero_pratica} è in stato {stato_corrente}.', 'is_active' => true,
        // NB: senza requires_confirmation — ora la conferma vale per tutte
    ]);
    $this->perito = Contatto::create(['tenant_id' => $this->tenant->id, 'tipo' => 'perito', 'nome' => 'Luigi Perito', 'email' => 'luigi@perito.it', 'telefono' => '3400000000']);
});

test('l\'anteprima mostra messaggio e destinatari anche per automazioni senza il flag richiede conferma', function () {
    $res = $this->actingAs($this->user)->postJson("/pratiche/{$this->pratica->id}/automations/preview", [
        'tenant_status_id' => $this->inviata->id, 'perito_contatto_id' => $this->perito->id,
    ])->assertOk();

    $a = $res->json('automations.0');
    expect($a['name'])->toBe('Avvisa cliente e perito')
        // lo stato nel messaggio è quello di DESTINAZIONE, non ancora salvato
        ->and($a['message'])->toBe('Ciao Mario Rossi, la pratica '.$this->pratica->id.' è in stato Inviata al perito.')
        ->and(collect($a['recipients'])->pluck('email')->all())->toBe(['mario@example.com', 'luigi@perito.it']);
});

test('nessuna anteprima se la pratica è già in quello stato', function () {
    $this->actingAs($this->user)->postJson("/pratiche/{$this->pratica->id}/automations/preview", ['tenant_status_id' => $this->nuova->id])
        ->assertOk()->assertJsonCount(0, 'automations');
});

test('cambio stato senza override invia ai destinatari calcolati', function () {
    $this->actingAs($this->user)->patchJson("/pratiche/{$this->pratica->id}/status", ['current_status_id' => $this->inviata->id])->assertOk();

    // (la coda dei test è sincrona: il job è già partito con i destinatari calcolati)
    expect(EmailLog::acrossAllTenants()->where('status', 'sent')->pluck('to_address')->all())->toBe(['mario@example.com']);
});

test('l\'utente può togliere un destinatario e aggiungerne uno extra', function () {
    Queue::fake();

    $this->actingAs($this->user)->patchJson("/pratiche/{$this->pratica->id}/status", [
        'current_status_id' => $this->inviata->id,
        'automation_overrides' => [$this->auto->id => [
            'send' => true,
            'recipients' => [
                ['name' => 'Studio Bianchi', 'email' => 'Extra@Example.com', 'phone' => null],
                ['name' => 'Non valido', 'email' => 'non-una-email', 'phone' => null], // scartato
            ],
            'cc' => ['capo@example.com'],
        ]],
    ])->assertOk();

    Queue::assertPushed(ExecuteAutomationJob::class, fn ($job) => $job->override === [
        'recipients' => [['name' => 'Studio Bianchi', 'email' => 'extra@example.com', 'phone' => null]],
        'cc' => ['capo@example.com'],
    ]);
});

test('con override il job invia solo ai destinatari scelti, con CC', function () {
    app()->call([new ExecuteAutomationJob($this->pratica, $this->auto, [
        'recipients' => [['name' => 'Studio Bianchi', 'email' => 'extra@example.com', 'phone' => null]],
        'cc' => ['capo@example.com'],
    ]), 'handle']);

    $log = EmailLog::acrossAllTenants()->where('status', 'sent')->get();
    expect($log)->toHaveCount(1)
        ->and($log[0]->to_address)->toBe('extra@example.com')
        ->and($log[0]->cc_addresses)->toBe(['capo@example.com']);
});

test('l\'utente può escludere una singola automazione, e bloccarle tutte', function () {
    Queue::fake();

    $this->actingAs($this->user)->patchJson("/pratiche/{$this->pratica->id}/status", [
        'current_status_id' => $this->inviata->id,
        'automation_overrides' => [$this->auto->id => ['send' => false]],
    ])->assertOk();
    Queue::assertNothingPushed();
    expect($this->pratica->fresh()->current_status_id)->toBe($this->inviata->id);

    $this->pratica->update(['current_status_id' => $this->nuova->id]);
    $this->actingAs($this->user)->patchJson("/pratiche/{$this->pratica->id}/status", [
        'current_status_id' => $this->inviata->id, 'skip_confirmable_automations' => true,
    ])->assertOk();
    Queue::assertNothingPushed();
});

test('il promemoria cliente programmato non parte da solo: resta da confermare, poi parte con i destinatari scelti', function () {
    Queue::fake();
    $this->tenant->update(['settings' => ['features' => ['clienti' => true]]]);
    $this->cliente->update(['custom_fields' => ['scadenza_patente' => today()->addDays(30)->toDateString()]]);
    $auto = Automation::create([
        'tenant_id' => $this->tenant->id, 'name' => 'Patente', 'trigger_type' => 'cliente_date_field', 'watched_field' => 'scadenza_patente',
        'days_before' => 30, 'channel' => 'email', 'recipients_to' => [['type' => 'cliente']], 'message_template' => 'Ciao {nome_cliente}, scade il {data_scadenza}', 'is_active' => true,
    ]);

    $this->artisan('app:process-cliente-date-reminders')->assertSuccessful();
    $this->artisan('app:process-cliente-date-reminders')->assertSuccessful(); // idempotente

    Queue::assertNothingPushed();
    $approval = \App\Models\AutomationApproval::acrossAllTenants()->sole();
    expect($approval->status)->toBe('pending');

    $this->actingAs($this->user)->get('/automazioni/da-confermare')->assertOk();

    $this->actingAs($this->user)->post("/automazioni/da-confermare/{$approval->id}/conferma", [
        'automation_overrides' => [$auto->id => ['send' => true, 'recipients' => [['name' => 'Altro', 'email' => 'altro@example.com']], 'cc' => []]],
    ])->assertRedirect();

    Queue::assertPushed(\App\Jobs\ExecuteClienteAutomationJob::class, fn ($j) => $j->override['recipients'][0]['email'] === 'altro@example.com');
    expect($approval->fresh()->status)->toBe('sent');
    $this->actingAs($this->user)->post("/automazioni/da-confermare/{$approval->id}/scarta")->assertStatus(409);
});

test('un promemoria da confermare si può scartare e non parte', function () {
    Queue::fake();
    $auto = Automation::create(['tenant_id' => $this->tenant->id, 'name' => 'P', 'trigger_type' => 'cliente_date_field', 'watched_field' => 'x', 'channel' => 'email', 'recipients_to' => [['type' => 'cliente']], 'message_template' => 'x', 'is_active' => true]);
    $approval = \App\Models\AutomationApproval::create(['tenant_id' => $this->tenant->id, 'automation_id' => $auto->id, 'cliente_id' => $this->cliente->id, 'field_name' => 'x', 'field_value' => today()->toDateString()]);

    $this->actingAs($this->user)->post("/automazioni/da-confermare/{$approval->id}/scarta")->assertRedirect();

    Queue::assertNothingPushed();
    expect($approval->fresh()->status)->toBe('discarded');
});
