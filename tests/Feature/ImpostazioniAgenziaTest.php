<?php

use App\Jobs\ExecuteAutomationJob;
use App\Models\{Automation, AutomationRun, Cliente, EmailLog, Ispezione, Pratica, Tenant, TenantStatus, User};
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

// Impostazioni dell'agenzia: stati con giorni di avviso, campi, categorie e automazioni create dall'agenzia.

beforeEach(function () {
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void
        {
            EmailLog::registra(array_merge($logContext, [
                'tenant_id' => $tenant->id, 'to_address' => $to, 'status' => 'sent', 'subject' => $mailable->envelope()->subject ?? null,
            ]));
        }
    });

    $this->tenant = Tenant::create(['name' => 'Agenzia Uno', 'settings' => ['default_notice_days' => 14]]);
    $this->nuova = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000000', 'order' => 0, 'is_initial' => true, 'notice_days' => 2]);
    $this->documenti = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Documenti mancanti', 'color' => '#111111', 'order' => 1, 'notice_days' => 3]);
    $this->attesa = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Attesa liquidazione', 'color' => '#222222', 'order' => 2]);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin', 'email' => 'admin@agenzia.it']);
    $this->collaboratore = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
    $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'email' => 'mario@example.com', 'telefono' => '333111222']);
    $this->pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->nuova->id]);
});

function automazione(array $extra = []): array
{
    return [
        'name' => 'Aggiorna il cliente', 'trigger_type' => 'status', 'tenant_status_id' => null, 'channel' => 'email',
        'recipients_to' => [['type' => 'cliente']], 'recipients_cc' => [], 'message_template' => 'Ciao {nome_cliente}, pratica {numero_pratica}: {stato_corrente}.',
        'document_category_ids' => [], 'is_active' => true, ...$extra,
    ];
}

// ── Accesso ───────────────────────────────────────────────────────────────

test('le impostazioni le vede l\'amministratore e il superadmin dentro l\'agenzia, non il collaboratore', function () {
    foreach (['generale', 'stati', 'campi', 'categorie', 'automazioni'] as $sezione) {
        $this->actingAs($this->admin)->get("/impostazioni/{$sezione}")->assertOk();
        $this->actingAs($this->collaboratore)->get("/impostazioni/{$sezione}")->assertForbidden();
    }

    $sa = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);
    $this->actingAs($sa)->get('/superadmin/tenants/'.$this->tenant->id.'/impostazioni/automazioni')->assertRedirect('/impostazioni/automazioni');
    $this->get('/impostazioni/automazioni')->assertOk();
});

// ── Stati e avvisi ────────────────────────────────────────────────────────

test('si salvano i giorni di avviso per stato; vuoto = predefiniti', function () {
    $this->actingAs($this->admin)->post('/impostazioni/stati', ['stati' => [
        ['id' => $this->nuova->id, 'name' => 'Nuova', 'color' => '#000000', 'is_closed' => false, 'is_initial' => true, 'notice_days' => 5],
        ['id' => $this->documenti->id, 'name' => 'Documenti mancanti', 'color' => '#111111', 'is_closed' => false, 'is_initial' => false, 'notice_days' => null],
        ['id' => $this->attesa->id, 'name' => 'Attesa liquidazione', 'color' => '#222222', 'is_closed' => false, 'is_initial' => false, 'notice_days' => 30],
        ['id' => null, 'name' => 'Chiusa', 'color' => '#333333', 'is_closed' => true, 'is_initial' => false, 'notice_days' => null],
    ]])->assertSessionHasNoErrors();

    expect($this->nuova->fresh()->notice_days)->toBe(5)
        ->and($this->documenti->fresh()->notice_days)->toBeNull()
        ->and($this->tenant->noticeDaysFor($this->documenti->id))->toBe(14)
        ->and($this->tenant->statuses()->where('name', 'Chiusa')->value('is_closed'))->toBeTrue();
});

test('non si elimina uno stato con sinistri; eliminandone uno vuoto le sue automazioni si spengono', function () {
    $auto = Automation::create([...automazione(['tenant_status_id' => $this->attesa->id]), 'tenant_id' => $this->tenant->id]);
    $base = fn ($senza) => collect([$this->nuova, $this->documenti, $this->attesa])->reject(fn ($s) => $s->id === $senza->id)
        ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color, 'is_initial' => (bool) $s->is_initial])->values()->all();

    $this->actingAs($this->admin)->post('/impostazioni/stati', ['stati' => $base($this->nuova)])->assertSessionHasErrors('stati');
    expect($this->nuova->fresh())->not->toBeNull();

    $this->post('/impostazioni/stati', ['stati' => $base($this->attesa)])->assertSessionHasNoErrors();
    expect($this->attesa->fresh())->toBeNull()->and($auto->fresh()->is_active)->toBeFalse();
});

test('il prossimo avviso usa i giorni dello stato: all\'apertura e a ogni cambio di stato', function () {
    $this->travelTo(now()->startOfDay());
    $this->actingAs($this->admin)->post('/pratiche', ['cliente_id' => $this->cliente->id])->assertRedirect();
    expect(Pratica::latest('id')->first()->data_prossimo_avviso->toDateString())->toBe(today()->addDays(2)->toDateString());

    $this->patchJson("/pratiche/{$this->pratica->id}/status", ['current_status_id' => $this->documenti->id])->assertOk();
    expect($this->pratica->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->addDays(3)->toDateString());

    $this->patchJson("/pratiche/{$this->pratica->id}/status", ['current_status_id' => $this->attesa->id])->assertOk();
    expect($this->pratica->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->addDays(14)->toDateString());
});

// ── Campi e categorie ─────────────────────────────────────────────────────

test('il nome tecnico di un campo nuovo nasce dall\'etichetta e quello esistente non cambia', function () {
    $this->tenant->update(['settings' => [...$this->tenant->settings, 'custom_fields_schema' => [['name' => 'data_visita', 'label' => 'Visita', 'type' => 'date', 'required' => false, 'options' => []]]]]);

    $this->actingAs($this->admin)->post('/impostazioni/campi', ['campi' => [
        ['name' => 'data_visita', 'label' => 'Data visita medica', 'type' => 'date', 'required' => false, 'options' => []],
        ['name' => null, 'label' => 'Tipo di danno', 'type' => 'select', 'required' => true, 'options' => ['Fisico', ' ', 'Materiale']],
    ], 'campi_cliente' => []])->assertSessionHasNoErrors();

    $schema = $this->tenant->fresh()->getCustomFieldsSchema();
    expect(collect($schema)->pluck('name')->all())->toBe(['data_visita', 'tipo_di_danno'])
        ->and($schema[1]['options'])->toBe(['Fisico', 'Materiale']);
});

test('non si toglie una data usata da un\'automazione attiva', function () {
    $this->tenant->update(['settings' => [...$this->tenant->settings, 'custom_fields_schema' => [['name' => 'data_visita', 'label' => 'Visita', 'type' => 'date']]]]);
    Automation::create([...automazione(['trigger_type' => 'date_reminder', 'watched_field' => 'data_visita']), 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->post('/impostazioni/campi', ['campi' => [], 'campi_cliente' => []])->assertSessionHasErrors('campi');
});

// ── Automazioni ───────────────────────────────────────────────────────────

test('l\'agenzia crea un\'automazione; i colleghi devono essere dell\'agenzia', function () {
    $estraneo = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Altra'])->id]);

    $this->actingAs($this->admin)->post('/impostazioni/automazioni', automazione(['recipients_to' => [['type' => 'user', 'user_id' => $estraneo->id]]]))
        ->assertSessionHasErrors('recipients_to.0.user_id');

    $this->post('/impostazioni/automazioni', automazione(['recipients_to' => [['type' => 'cliente'], ['type' => 'perito']], 'subject' => 'Novità sul sinistro {numero_pratica}']))
        ->assertRedirect();
    $a = Automation::where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($a->recipients_to)->toBe([['type' => 'cliente'], ['type' => 'perito']])->and($a->subject)->toBe('Novità sul sinistro {numero_pratica}');

    $this->get("/impostazioni/automazioni/{$a->id}")->assertInertia(fn (Assert $p) => $p
        ->component('Impostazioni/Automazioni/Editor')->where('automazione.recipients_to.1.type', 'perito'));
});

test('"Qualsiasi stato" scatta a ogni cambio di stato (non se lo stato resta uguale) con l\'oggetto scelto', function () {
    Automation::create([...automazione(['subject' => 'Aggiornamento pratica {numero_pratica}']), 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->patchJson("/pratiche/{$this->pratica->id}/status", ['current_status_id' => $this->nuova->id])->assertOk();
    expect(EmailLog::where('tipo', 'automazione')->count())->toBe(0);

    $this->patchJson("/pratiche/{$this->pratica->id}/status", ['current_status_id' => $this->documenti->id])->assertOk();
    $log = EmailLog::where('tipo', 'automazione')->sole();
    expect($log->to_address)->toBe('mario@example.com')->and($log->subject)->toBe("Aggiornamento pratica {$this->pratica->id}");
});

test('"Nuovo sinistro aperto" parte all\'apertura', function () {
    Queue::fake();
    Automation::create([...automazione(['trigger_type' => 'pratica_created']), 'tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin)->post('/pratiche', ['cliente_id' => $this->cliente->id]);
    Queue::assertPushed(ExecuteAutomationJob::class, 1);
});

test('promemoria il giorno prima della perizia: una volta sola, solo sinistri aperti', function () {
    Queue::fake();
    $auto = Automation::create([...automazione(['trigger_type' => 'date_reminder', 'watched_field' => 'data_appuntamento', 'offset_days' => -1]), 'tenant_id' => $this->tenant->id]);
    Ispezione::create(['tenant_id' => $this->tenant->id, 'pratica_id' => $this->pratica->id, 'stato' => 'pianificata', 'data_appuntamento' => now()->addDay()->setTime(10, 30)]);

    $chiusa = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Chiusa', 'color' => '#333333', 'order' => 9, 'is_closed' => true]);
    $altra = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $chiusa->id]);
    Ispezione::create(['tenant_id' => $this->tenant->id, 'pratica_id' => $altra->id, 'stato' => 'pianificata', 'data_appuntamento' => now()->addDay()]);

    $this->artisan('app:process-pratica-date-reminders')->assertSuccessful();
    $this->artisan('app:process-pratica-date-reminders')->assertSuccessful();

    Queue::assertPushed(ExecuteAutomationJob::class, 1);
    Queue::assertPushed(ExecuteAutomationJob::class, fn ($job) => $job->pratica->id === $this->pratica->id && $job->automation->is($auto));
    expect(AutomationRun::count())->toBe(1);
});

test('anteprima con un sinistro vero e prova inviata solo a chi configura', function () {
    Ispezione::create(['tenant_id' => $this->tenant->id, 'pratica_id' => $this->pratica->id, 'stato' => 'pianificata', 'data_appuntamento' => '2026-12-01 10:30:00']);
    $dati = automazione(['trigger_type' => 'date_field', 'watched_field' => 'data_appuntamento', 'subject' => 'Perizia {numero_pratica}', 'message_template' => 'Perizia il {data_perizia}', 'pratica_id' => $this->pratica->id]);

    $this->actingAs($this->admin)->postJson('/impostazioni/automazioni/anteprima', $dati)->assertOk()
        ->assertJsonPath('piano.subject', "Perizia {$this->pratica->id}")
        ->assertJsonPath('piano.message', 'Perizia il 01/12/2026 alle 10:30')
        ->assertJsonPath('piano.recipients.0.email', 'mario@example.com');

    $this->postJson('/impostazioni/automazioni/anteprima', [...$dati, 'message_template' => ''])->assertStatus(422)->assertJsonStructure(['errori' => ['message_template']]);

    $this->postJson('/impostazioni/automazioni/prova', $dati)->assertOk();
    expect(EmailLog::where('tipo', 'test')->sole()->to_address)->toBe('admin@agenzia.it');
});

test('il superadmin salva ancora dal suo pannello, anche con perito tra i destinatari', function () {
    $sa = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);
    $this->actingAs($sa)->post("/superadmin/tenants/{$this->tenant->id}/automations", automazione(['recipients_to' => [['type' => 'perito'], ['type' => 'gestore']]]))
        ->assertSessionHasNoErrors();
    expect(Automation::where('tenant_id', $this->tenant->id)->sole()->recipients_to)->toBe([['type' => 'perito'], ['type' => 'gestore']]);
});

test('l\'anteprima segnala i dati vuoti nel sinistro di esempio', function () {
    $this->actingAs($this->admin)->postJson('/impostazioni/automazioni/anteprima', automazione(['message_template' => 'Perizia il {data_perizia}', 'pratica_id' => $this->pratica->id]))
        ->assertOk()->assertJsonPath('piano.mancanti', ['Data del sopralluogo/perizia']);
});

test('la data del prossimo avviso si cambia a mano dalla scheda (non nel passato, vuota = nessun avviso)', function () {
    $this->actingAs($this->collaboratore)->patch("/pratiche/{$this->pratica->id}/avviso", ['data_prossimo_avviso' => today()->addDays(10)->toDateString()])
        ->assertSessionHasNoErrors();
    expect($this->pratica->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->addDays(10)->toDateString());

    $this->patch("/pratiche/{$this->pratica->id}/avviso", ['data_prossimo_avviso' => today()->subDay()->toDateString()])
        ->assertSessionHasErrors('data_prossimo_avviso');

    $this->patch("/pratiche/{$this->pratica->id}/avviso", ['data_prossimo_avviso' => null])->assertSessionHasNoErrors();
    expect($this->pratica->fresh()->data_prossimo_avviso)->toBeNull();

    $esterno = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'external']);
    $this->actingAs($esterno)->patch("/pratiche/{$this->pratica->id}/avviso", ['data_prossimo_avviso' => null])->assertForbidden();

    $altro = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Altra2'])->id, 'role' => 'tenant-admin']);
    $this->actingAs($altro)->patch("/pratiche/{$this->pratica->id}/avviso", ['data_prossimo_avviso' => null])->assertNotFound();
});

test('le categorie mai configurate contano come attive: si vedono nell\'editor e si allegano alle automazioni', function () {
    $foto = \App\Models\DocumentCategory::create(['name' => 'Foto danno']);
    $spenta = \App\Models\DocumentCategory::create(['name' => 'Interna']);
    $this->tenant->documentCategories()->attach($spenta->id, ['is_enabled' => false, 'max_file_size_mb' => 10]);

    $this->actingAs($this->admin)->get('/impostazioni/automazioni/nuova')->assertInertia(fn (Assert $p) => $p
        ->where('opzioni.categorie', fn ($c) => collect($c)->pluck('name')->contains('Foto danno') && ! collect($c)->pluck('name')->contains('Interna')));
    $this->get('/impostazioni/categorie')->assertInertia(fn (Assert $p) => $p
        ->where('categorie', fn ($c) => collect($c)->firstWhere('name', 'Foto danno')['is_enabled'] === true && collect($c)->firstWhere('name', 'Interna')['is_enabled'] === false));

    $this->post('/impostazioni/automazioni', automazione(['document_category_ids' => [$foto->id]]))->assertSessionHasNoErrors();
    expect(Automation::where('tenant_id', $this->tenant->id)->sole()->documentCategories->pluck('name')->all())->toBe(['Foto danno']);
});
