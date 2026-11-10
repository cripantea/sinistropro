<?php

use App\Models\{Cliente, EmailMessage, EmailThread, Pratica, Tenant, TenantStatus, User};
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;
use Inertia\Testing\AssertableInertia as Assert;

// Corrispondenza email dentro il sinistro e numero del sinistro scelto dall'agenzia.

beforeEach(function () {
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void {}
    });
    $this->tenant = Tenant::create(['name' => 'Agenzia Posta', 'settings' => ['custom_fields_schema' => [['name' => 'numero_sinistro', 'label' => 'N. sinistro compagnia', 'type' => 'text']]]]);
    $stato = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Aperto', 'color' => '#000000', 'order' => 0, 'is_initial' => true]);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Anna Bianchi', 'email' => 'Anna@Example.com']);
    $altroCliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Altro', 'email' => 'altro@example.com']);
    $this->pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $stato->id, 'custom_fields' => ['numero_sinistro' => 'UNI-2026-778899']]);
    $this->altra = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $altroCliente->id, 'current_status_id' => $stato->id]);
});

function emailRicevuta(int $tenantId, string $da, string $oggetto, array $extra = []): EmailMessage
{
    $thread = EmailThread::firstOrCreate(['tenant_id' => $tenantId, 'counterpart_email' => $da]);

    return EmailMessage::create([
        'tenant_id' => $tenantId, 'email_thread_id' => $thread->id, 'direction' => 'inbound', 'folder' => 'inbox',
        'from_address' => $da, 'to_addresses' => ['sinistri@agenzia.it'], 'subject' => $oggetto, 'body_text' => 'testo',
        'email_timestamp' => now(), ...$extra,
    ]);
}

test('nel sinistro si vedono le email del cliente, quelle col numero in oggetto e quelle scritte dal sinistro', function () {
    $this->tenant->update(['settings' => [...$this->tenant->settings, 'numero_sinistro_campo' => 'numero_sinistro']]);
    $delCliente = emailRicevuta($this->tenant->id, 'anna@example.com', 'Foto auto');
    $colNumero = emailRicevuta($this->tenant->id, 'liquidazioni@unipol.it', 'Rif. UNI-2026-778899 offerta');
    emailRicevuta($this->tenant->id, 'spam@example.com', 'Altro');
    emailRicevuta($this->tenant->id, 'anna@example.com', 'Per l\'altro sinistro', ['pratica_id' => $this->altra->id]);

    $this->actingAs($this->admin)->postJson('/email/compose', ['to' => 'perito@studio.it', 'subject' => 'Perizia', 'body' => 'Ciao', 'pratica_id' => $this->pratica->id])->assertOk();
    $scritta = EmailMessage::where('subject', 'Perizia')->sole();
    expect($scritta->pratica_id)->toBe($this->pratica->id);

    $risposta = $this->getJson("/pratiche/{$this->pratica->id}/email")->assertOk()
        ->assertJsonPath('oggetto', 'Sinistro UNI-2026-778899 — Anna Bianchi')
        ->assertJsonPath('destinatari.0.email', 'Anna@Example.com');
    expect(collect($risposta->json('messages'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$delCliente->id, $colNumero->id, $scritta->id])->sort()->values()->all());

    // La risposta dal thread in Email resta nel sinistro.
    $thread = EmailThread::where('counterpart_email', 'perito@studio.it')->sole();
    $this->postJson("/email/threads/{$thread->id}/reply", ['body' => 'Seguito'])->assertOk();
    expect(EmailMessage::latest('id')->first()->pratica_id)->toBe($this->pratica->id);

    // Non si legano email a sinistri di un'altra agenzia.
    $estranea = Tenant::create(['name' => 'Estranea']);
    $suo = Pratica::create(['tenant_id' => $estranea->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $this->cliente->id]);
    $this->postJson('/email/compose', ['to' => 'x@y.it', 'subject' => 'X', 'body' => 'X', 'pratica_id' => $suo->id])->assertNotFound();
});

test('il numero del sinistro può essere quello della compagnia: si vede ovunque, vuoto = #id', function () {
    expect($this->pratica->fresh()->numero)->toBe('#'.$this->pratica->id);

    $this->actingAs($this->admin)->post('/impostazioni/generale', ['name' => 'Agenzia Posta', 'default_notice_days' => 14, 'numero_sinistro_campo' => 'numero_sinistro'])->assertSessionHasNoErrors();
    $this->post('/impostazioni/generale', ['name' => 'Agenzia Posta', 'default_notice_days' => 14, 'numero_sinistro_campo' => 'inventato'])->assertSessionHasErrors('numero_sinistro_campo');

    expect($this->pratica->fresh()->numero)->toBe('UNI-2026-778899')
        ->and($this->altra->fresh()->numero)->toBe('#'.$this->altra->id)
        ->and(app(\App\Services\AutomationPlanner::class)->compilePratica('n. {numero_pratica}', $this->pratica->fresh(), null))->toBe('n. UNI-2026-778899');

    $this->get("/pratiche/{$this->pratica->id}")->assertInertia(fn (Assert $p) => $p->where('pratica.numero', 'UNI-2026-778899'));
    $this->get('/pratiche/kanban')->assertInertia(fn (Assert $p) => $p->where('pratiche', fn ($l) => collect($l)->contains('numero', 'UNI-2026-778899')));
    $this->get('/pratiche?search=778899')->assertInertia(fn (Assert $p) => $p->where('pratiche.data.0.numero', 'UNI-2026-778899'));
});

test('casella email e WhatsApp si collegano anche quando l\'agenzia è in sola lettura', function () {
    $this->tenant->forceFill(['billing_mode' => 'stripe', 'subscription_status' => null, 'free_until' => null])->save();
    expect($this->tenant->fresh()->hasAccess())->toBeFalse();

    $this->actingAs($this->admin)->delete('/impostazioni/email')->assertRedirect();
    // Anche il salvataggio del collegamento WhatsApp passa (poi decide la verifica con FusionWA).
    $this->app->instance(\App\Services\FusionWaConnectionClient::class, \Mockery::mock(\App\Services\FusionWaConnectionClient::class, fn ($m) => $m->shouldReceive('status')->andReturn(null)));
    $this->postJson('/whatsapp/embedded-signup', ['waba_id' => 'W', 'phone_number_id' => 'P', 'phone_number' => '+39 1'])->assertStatus(503);
    $this->post('/pratiche', [])->assertSessionHas('error');
});
