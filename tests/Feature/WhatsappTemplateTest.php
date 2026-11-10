<?php

use App\Jobs\ExecuteAutomationJob;
use App\Models\{Automation, Cliente, Contatto, Pratica, Tenant, TenantStatus, User, WhatsappConversation, WhatsappMessage, WhatsappSession, WhatsappTemplate};
use App\Services\TenantMailerResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;

// Template WhatsApp (come Fusion CRM, con le variabili di SinistroPro), invio nelle automazioni e in chat, rubrica nei destinatari.

beforeEach(function () {
    config(['services.fusionwa.base_url' => 'https://fusionwa.test', 'services.fusionwa.api_key' => 'k', 'services.fusionwa.api_secret' => 's']);
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void {}
    });
    $this->tenant = Tenant::create(['name' => 'Agenzia WA']);
    $this->stato = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Aperto', 'color' => '#000000', 'order' => 0, 'is_initial' => true]);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    WhatsappSession::create(['tenant_id' => $this->tenant->id, 'status' => 'active', 'waba_id' => 'W1', 'phone_number_id' => 'P1']);
    $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'telefono' => '333 111 2222']);
    $this->pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->stato->id]);
});

test('il template si scrive con le variabili di SinistroPro e va a Meta come {{1}}, {{2}} con gli esempi', function () {
    Http::fake(['fusionwa.test/api/v1/templates' => Http::response(['status' => 'success', 'template' => ['externalId' => 'E1', 'status' => 'PENDING', 'category' => 'UTILITY']])]);

    $this->actingAs($this->admin)->post('/impostazioni/whatsapp-template', [
        'name' => 'Sollecito Documenti', 'category' => 'UTILITY',
        'corpo' => 'Gentile {nome_cliente}, la pratica {numero_pratica} attende i documenti, {nome_cliente}. Grazie',
    ])->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r['name'] === 'sollecito_documenti' && $r['externalCustomerId'] === (string) $this->tenant->id
        && $r['bodyText'] === 'Gentile {{1}}, la pratica {{2}} attende i documenti, {{1}}. Grazie'
        && $r['bodyExamples'] === ['Mario Rossi', '12345']);

    $t = WhatsappTemplate::sole();
    expect($t->status)->toBe('PENDING')->and($t->variabili)->toBe(['1' => '{nome_cliente}', '2' => '{numero_pratica}'])->and($t->variable_count)->toBe(2);

    // Meta non accetta variabili all'inizio o alla fine.
    $this->post('/impostazioni/whatsapp-template', ['name' => 'x', 'category' => 'UTILITY', 'corpo' => 'Ciao {nome_cliente}'])->assertSessionHasErrors('corpo');
});

test('sincronizza: stati aggiornati da Meta, quelli spariti si tolgono', function () {
    WhatsappTemplate::create(['tenant_id' => $this->tenant->id, 'name' => 'vecchio', 'body' => 'x', 'status' => 'APPROVED']);
    WhatsappTemplate::create(['tenant_id' => $this->tenant->id, 'name' => 'sollecito', 'body' => 'Ciao {{1}} ok', 'corpo' => 'Ciao {nome_cliente} ok', 'variabili' => ['1' => '{nome_cliente}'], 'variable_count' => 1]);
    Http::fake(['fusionwa.test/api/v1/templates*' => Http::response(['status' => 'success', 'templates' => [
        ['externalId' => 'E1', 'name' => 'sollecito', 'language' => 'it', 'category' => 'UTILITY', 'status' => 'APPROVED', 'body' => 'Ciao {{1}} ok', 'variableCount' => 1],
        ['externalId' => 'E2', 'name' => 'da_meta', 'language' => 'it', 'category' => 'MARKETING', 'status' => 'REJECTED', 'body' => 'Promo {{1}} qui', 'variableCount' => 1],
    ]])]);

    $this->actingAs($this->admin)->post('/impostazioni/whatsapp-template/sincronizza')->assertSessionHas('success');

    expect(WhatsappTemplate::orderBy('name')->pluck('status', 'name')->all())->toBe(['da_meta' => 'REJECTED', 'sollecito' => 'APPROVED'])
        ->and(WhatsappTemplate::where('name', 'sollecito')->value('corpo'))->toBe('Ciao {nome_cliente} ok');
});

test('l\'automazione WhatsApp parte col template approvato, ai contatti della rubrica, e finisce nella chat', function () {
    Http::fake(['fusionwa.test/api/v1/messages/send' => Http::response(['status' => 'success', 'messageId' => 'wamid.1'])]);
    $template = WhatsappTemplate::create(['tenant_id' => $this->tenant->id, 'name' => 'aggiornamento', 'body' => 'Gentile {{1}}, pratica {{2}} aggiornata.', 'variabili' => ['1' => '{nome_cliente}', '2' => '{numero_pratica}'], 'variable_count' => 2, 'status' => 'APPROVED']);
    $perito = Contatto::create(['tenant_id' => $this->tenant->id, 'nome' => 'Studio Bianchi', 'tags' => ['perito'], 'telefono' => '+39 340 999 8888', 'is_active' => true]);

    $this->actingAs($this->admin)->post('/impostazioni/automazioni', [
        'name' => 'Aggiorna', 'trigger_type' => 'status', 'tenant_status_id' => $this->stato->id, 'channel' => 'whatsapp',
        'whatsapp_template_id' => $template->id, 'whatsapp_params' => ['{nome_cliente}', '{numero_pratica}'],
        'recipients_to' => [['type' => 'cliente'], ['type' => 'contatto', 'contatto_id' => $perito->id]], 'recipients_cc' => [],
        'message_template' => 'Testo per email', 'document_category_ids' => [],
    ])->assertSessionHasNoErrors();
    $auto = Automation::where('name', 'Aggiorna')->sole();

    (new ExecuteAutomationJob($this->pratica, $auto))->handle(app(TenantMailerResolver::class), app(\App\Services\AutomationPlanner::class));

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => $r['toPhoneNumber'] === '393331112222' && $r['template']['name'] === 'aggiornamento'
        && $r['template']['bodyParams'] === ['Mario Rossi', (string) $this->pratica->id]);
    Http::assertSent(fn (Request $r) => $r['toPhoneNumber'] === '393409998888');
    expect(WhatsappMessage::where('status', 'sent')->count())->toBe(2)
        ->and(WhatsappConversation::where('phone_number', '393331112222')->value('last_message_preview'))->toBe("Gentile Mario Rossi, pratica {$this->pratica->id} aggiornata.");

    // Un contatto di un'altra agenzia non si può scegliere.
    $estraneo = Contatto::create(['tenant_id' => Tenant::create(['name' => 'Altra'])->id, 'nome' => 'X', 'telefono' => '1', 'is_active' => true]);
    $this->put("/impostazioni/automazioni/{$auto->id}", [
        'name' => 'Aggiorna', 'trigger_type' => 'status', 'channel' => 'whatsapp', 'recipients_to' => [['type' => 'contatto', 'contatto_id' => $estraneo->id]],
        'message_template' => 'x', 'document_category_ids' => [],
    ])->assertSessionHasErrors('recipients_to.0.contatto_id');
});

test('in chat: fuori dalle 24 ore si propone il template, già compilato coi dati del sinistro', function () {
    Http::fake(['fusionwa.test/api/v1/messages/send' => Http::response(['status' => 'success', 'messageId' => 'wamid.2'])]);
    WhatsappTemplate::create(['tenant_id' => $this->tenant->id, 'name' => 'sollecito', 'body' => 'Ciao {{1}}, ti scriviamo.', 'variabili' => ['1' => '{nome_cliente}'], 'variable_count' => 1, 'status' => 'APPROVED']);
    $conv = WhatsappConversation::create(['tenant_id' => $this->tenant->id, 'phone_number' => '393331112222', 'pratica_id' => $this->pratica->id]);

    $r = $this->actingAs($this->admin)->getJson("/whatsapp/conversations/{$conv->id}/messages")->assertOk()
        ->assertJsonPath('finestraAperta', false)
        ->assertJsonPath('templates.0.valori.0', 'Mario Rossi');
    $id = $r->json('templates.0.id');

    $this->postJson("/whatsapp/conversations/{$conv->id}/messages", ['template_id' => $id, 'params' => ['']])->assertStatus(422);
    $this->postJson("/whatsapp/conversations/{$conv->id}/messages", ['template_id' => $id, 'params' => ['Mario']])->assertOk()
        ->assertJsonPath('message.body', 'Ciao Mario, ti scriviamo.')->assertJsonPath('errore', null);

    WhatsappMessage::create(['tenant_id' => $this->tenant->id, 'whatsapp_conversation_id' => $conv->id, 'direction' => 'inbound', 'body' => 'ok', 'wa_timestamp' => now()->subHours(2)]);
    $this->getJson("/whatsapp/conversations/{$conv->id}/messages")->assertJsonPath('finestraAperta', true);
});

test('se FusionWA rifiuta, il messaggio resta in chat come non inviato e si vede il motivo', function () {
    Http::fake(['fusionwa.test/api/v1/messages/send' => Http::response(['error' => 'Re-engagement message: finestra di 24 ore chiusa'], 400)]);
    $conv = WhatsappConversation::create(['tenant_id' => $this->tenant->id, 'phone_number' => '393331112222']);

    $this->actingAs($this->admin)->postJson("/whatsapp/conversations/{$conv->id}/messages", ['body' => 'Ciao'])->assertOk()
        ->assertJsonPath('message.status', 'failed')->assertJsonPath('errore', 'Re-engagement message: finestra di 24 ore chiusa');
});

test('destinatario "studio peritale del sinistro": l\'automazione scrive a quello scelto nella scheda', function () {
    $studio = Contatto::create(['tenant_id' => $this->tenant->id, 'nome' => 'Studio Magni', 'tags' => ['studio peritale'], 'email' => 'info@magni.it', 'is_active' => true]);
    $perito = Contatto::create(['tenant_id' => $this->tenant->id, 'nome' => 'Perito X', 'tags' => ['perito'], 'email' => 'x@p.it', 'is_active' => true]);

    $this->actingAs($this->admin)->get("/pratiche/{$this->pratica->id}")
        ->assertInertia(fn ($p) => $p->where('categorieRubrica.0.tag', 'studio peritale')->has('categorieRubrica', 1));

    $this->patchJson("/pratiche/{$this->pratica->id}/contatti", ['contatti' => ['studio peritale' => $perito->id]])->assertStatus(422);
    $this->patchJson("/pratiche/{$this->pratica->id}/contatti", ['contatti' => ['Studio Peritale' => $studio->id]])->assertOk();
    expect($this->pratica->fresh()->contatti)->toBe(['studio peritale' => $studio->id]);

    $this->post('/impostazioni/automazioni', [
        'name' => 'Allo studio', 'trigger_type' => 'status', 'tenant_status_id' => $this->stato->id, 'channel' => 'email',
        'recipients_to' => [['type' => 'categoria', 'categoria' => 'studio peritale']], 'recipients_cc' => [],
        'message_template' => 'Buongiorno {nome_destinatario}', 'document_category_ids' => [],
    ])->assertSessionHasNoErrors();
    $auto = Automation::where('name', 'Allo studio')->sole();

    $piano = app(\App\Services\AutomationPlanner::class)->planPratica($this->pratica->fresh(), $auto);
    expect($piano['recipients'][0]['email'])->toBe('info@magni.it')->and($piano['recipients'][0]['name'])->toBe('Studio Magni');
});

test('in apertura si scelgono (facoltativi) studio peritale, perito e carrozzeria dalla rubrica', function () {
    $studio = Contatto::create(['tenant_id' => $this->tenant->id, 'nome' => 'Studio Magni', 'tags' => ['studio peritale'], 'is_active' => true]);
    $perito = Contatto::create(['tenant_id' => $this->tenant->id, 'nome' => 'Perito X', 'tags' => ['perito'], 'is_active' => true]);
    $carr = Contatto::create(['tenant_id' => $this->tenant->id, 'nome' => 'Carrozzeria Y', 'tags' => ['carrozzeria'], 'is_active' => true]);

    $this->actingAs($this->admin)->get('/pratiche/create')
        ->assertInertia(fn ($p) => $p->where('rubrica.0.tag', 'studio peritale')->where('rubrica.1.tag', 'perito')->where('rubrica.2.tag', 'carrozzeria'));

    // Senza scegliere nulla si apre lo stesso.
    $this->post('/pratiche', ['cliente_id' => $this->cliente->id])->assertSessionHasNoErrors();

    $this->post('/pratiche', ['cliente_id' => $this->cliente->id, 'perito_contatto_id' => $perito->id, 'carrozzeria_contatto_id' => $carr->id,
        'contatti' => ['studio peritale' => $studio->id]])->assertSessionHasNoErrors();
    $p = Pratica::latest('id')->first();
    expect($p->contatti)->toBe(['studio peritale' => $studio->id])
        ->and($p->ispezioni()->first()->perito_contatto_id)->toBe($perito->id)
        ->and($p->ispezioni()->first()->carrozzeria_contatto_id)->toBe($carr->id);

    $this->post('/pratiche', ['cliente_id' => $this->cliente->id, 'carrozzeria_contatto_id' => $perito->id])->assertSessionHasErrors('carrozzeria_contatto_id');
});

test('il pulsante Salva di perito e carrozzeria nella scheda non dà più errore 500', function () {
    $perito = Contatto::create(['tenant_id' => $this->tenant->id, 'nome' => 'Perito X', 'tags' => ['perito'], 'is_active' => true]);

    $this->actingAs($this->admin)->from("/pratiche/{$this->pratica->id}")
        ->post("/pratiche/{$this->pratica->id}/ispezioni", ['perito_contatto_id' => $perito->id, 'carrozzeria_contatto_id' => null])
        ->assertRedirect("/pratiche/{$this->pratica->id}")->assertSessionHas('success');
    expect($this->pratica->ispezioni()->first()->perito_contatto_id)->toBe($perito->id);
});
