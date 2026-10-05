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
    $this->perito = Contatto::create(['tenant_id' => $this->tenant->id, 'tags' => ['perito'], 'nome' => 'Luigi Perito', 'email' => 'luigi@perito.it', 'telefono' => '3400000000']);
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

test('il promemoria sulla data del cliente parte in automatico, senza conferma', function () {
    Queue::fake();
    $this->tenant->update(['settings' => ['features' => ['clienti' => true]]]);
    $this->cliente->update(['custom_fields' => ['scadenza_patente' => today()->addDays(30)->toDateString()]]);
    Automation::create([
        'tenant_id' => $this->tenant->id, 'name' => 'Patente', 'trigger_type' => 'cliente_date_field', 'watched_field' => 'scadenza_patente',
        'days_before' => 30, 'channel' => 'email', 'recipients_to' => [['type' => 'cliente']], 'message_template' => 'x', 'is_active' => true,
    ]);

    $this->artisan('app:process-cliente-date-reminders')->assertSuccessful();

    Queue::assertPushed(\App\Jobs\ExecuteClienteAutomationJob::class, 1);
});

test('nel sinistro si scelgono solo i contatti col tag giusto; un contatto può avere più tag', function () {
    $carr  = Contatto::create(['tenant_id' => $this->tenant->id, 'tags' => ['carrozzeria'], 'nome' => 'Solo Carrozzeria']);
    $both  = Contatto::create(['tenant_id' => $this->tenant->id, 'tags' => ['perito', 'carrozzeria', 'vip'], 'nome' => 'Tuttofare']);

    // un contatto senza tag "perito" non è assegnabile come perito...
    $this->actingAs($this->user)->postJson("/pratiche/{$this->pratica->id}/ispezioni", ['perito_contatto_id' => $carr->id])
        ->assertSessionHasErrors('perito_contatto_id');
    // ...mentre uno con entrambi i tag lo è, sia come perito sia come carrozzeria
    $this->actingAs($this->user)->postJson("/pratiche/{$this->pratica->id}/ispezioni", ['perito_contatto_id' => $both->id, 'carrozzeria_contatto_id' => $both->id])->assertOk();
    expect(Ispezione::first()->perito_contatto_id)->toBe($both->id);

    // la pagina del sinistro propone i contatti per tag
    $page = $this->actingAs($this->user)->get("/pratiche/{$this->pratica->id}")->assertOk()->viewData('page')['props'];
    expect(collect($page['periti'])->pluck('nome')->sort()->values()->all())->toBe(['Luigi Perito', 'Tuttofare'])
        ->and(collect($page['carrozzerie'])->pluck('nome')->sort()->values()->all())->toBe(['Solo Carrozzeria', 'Tuttofare']);
});

test('la rubrica si filtra per tag e l\'anteprima automazioni offre i contatti da aggiungere', function () {
    $this->actingAs($this->user)->get('/rubrica?tag=Perito')->assertOk()
        ->assertInertia(fn ($p) => $p->where('tag', 'perito')->has('contatti', 1)->where('tags', ['carrozzeria', 'perito']));

    $res = $this->actingAs($this->user)->postJson("/pratiche/{$this->pratica->id}/automations/preview", ['tenant_status_id' => $this->inviata->id])->assertOk();
    expect($res->json('rubrica.0.nome'))->toBe('Luigi Perito');
});

test('la conferma mostra gli allegati che l\'automazione invierà, o avvisa se non ce ne sono', function () {
    $cat   = \App\Models\DocumentCategory::create(['name' => 'Foto danni']);
    $altra = \App\Models\DocumentCategory::create(['name' => 'Perizie']);
    $this->auto->documentCategories()->sync([$cat->id, $altra->id]);
    $file = \App\Models\Allegato::create(['pratica_id' => $this->pratica->id, 'tenant_id' => $this->tenant->id, 'nome_file' => 'foto-paraurti.jpg', 's3_key' => 'x/foto.jpg', 'document_category_id' => $cat->id, 'source' => 'caricato']);
    \App\Models\Allegato::create(['pratica_id' => $this->pratica->id, 'tenant_id' => $this->tenant->id, 'nome_file' => 'altro.pdf', 's3_key' => 'x/altro.pdf', 'document_category_id' => null, 'source' => 'caricato']);

    $a = $this->actingAs($this->user)->postJson("/pratiche/{$this->pratica->id}/automations/preview", ['tenant_status_id' => $this->inviata->id])
        ->assertOk()->json('automations.0');

    expect($a['documents'])->toBe([['id' => $file->id, 'nome_file' => 'foto-paraurti.jpg', 'categoria' => 'Foto danni']])
        ->and($a['document_categories'])->toBe(['Foto danni', 'Perizie']);

    // Categorie collegate ma nessun file corrispondente → elenco vuoto, categorie presenti (la UI avvisa)
    \App\Models\Allegato::where('id', $file->id)->delete();
    $b = $this->actingAs($this->user)->postJson("/pratiche/{$this->pratica->id}/automations/preview", ['tenant_status_id' => $this->inviata->id])->json('automations.0');
    expect($b['documents'])->toBe([])->and($b['document_categories'])->not->toBe([]);
});
