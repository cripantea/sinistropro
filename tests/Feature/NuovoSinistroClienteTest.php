<?php

use App\Models\{Cliente, Pratica, Tenant, TenantStatus, User, Veicolo};

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Malacrida']);
    TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->rossi = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'ROSSI MARIO', 'telefono' => '3331112222', 'codice_esterno' => '000001']);
    $this->auto = Veicolo::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->rossi->id, 'targa' => 'GT066RR', 'marca' => 'RENAULT']);
    Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'BIANCHI ANNA']);
});

function abilitaVeicoli(Tenant $t): void
{
    $t->update(['settings' => ['features' => ['veicoli' => true]]]);
}

test('la pagina non carica più tutti i clienti: si cercano mentre si digita, anche per targa e telefono', function () {
    $this->actingAs($this->user)->get('/pratiche/create')->assertOk()
        ->assertInertia(fn ($page) => $page->missing('clienti')->where('chiediVeicolo', false));

    expect($this->getJson('/clienti/cerca?q=ross')->json('risultati.0.nome'))->toBe('ROSSI MARIO');
    expect($this->getJson('/clienti/cerca?q=mario rossi')->json('risultati.0.nome'))->toBe('ROSSI MARIO');
    expect($this->getJson('/clienti/cerca?q=111 2222')->json('risultati.0.nome'))->toBe('ROSSI MARIO');
    $perTarga = $this->getJson('/clienti/cerca?q=gt066')->json('risultati.0');
    expect($perTarga['nome'])->toBe('ROSSI MARIO')->and($perTarga['veicolo']['id'])->toBe($this->auto->id);
    expect($this->getJson('/clienti/cerca?q=r')->json('risultati'))->toBe([]);
});

test('la ricerca non mostra clienti di altri tenant', function () {
    $altro = Tenant::create(['name' => 'Altro']);
    Cliente::create(['tenant_id' => $altro->id, 'nome' => 'ROSSI ESTRANEO']);

    $nomi = collect($this->actingAs($this->user)->getJson('/clienti/cerca?q=rossi')->json('risultati'))->pluck('nome');
    expect($nomi->all())->toBe(['ROSSI MARIO']);
});

test('dalla scheda si completano i dati mancanti del cliente e si aggiunge un veicolo', function () {
    $this->actingAs($this->user)->getJson("/clienti/{$this->rossi->id}/scheda")->assertOk()
        ->assertJsonPath('veicoli.0.targa', 'GT066RR');

    $this->patchJson("/clienti/{$this->rossi->id}/dati", ['email' => 'mario@example.com', 'codice_fiscale' => 'rssmra80a01f704x'])->assertOk();
    expect($this->rossi->fresh()->only(['email', 'codice_fiscale', 'telefono']))
        ->toBe(['email' => 'mario@example.com', 'codice_fiscale' => 'RSSMRA80A01F704X', 'telefono' => '3331112222']);

    $this->patchJson("/clienti/{$this->rossi->id}/dati", ['email' => 'non-una-email'])->assertStatus(422);

    $this->postJson("/clienti/{$this->rossi->id}/veicoli", ['targa' => 'ab 123 cd', 'marca' => 'DACIA'])->assertCreated()
        ->assertJsonPath('veicolo.targa', 'AB123CD');
    expect($this->rossi->veicoli()->count())->toBe(2);
});

test('con la funzione veicoli il sinistro richiede un veicolo del cliente scelto', function () {
    abilitaVeicoli($this->tenant);
    $bianchi = Cliente::where('nome', 'BIANCHI ANNA')->first();
    $this->actingAs($this->user);

    $this->post('/pratiche', ['cliente_id' => $this->rossi->id])->assertSessionHasErrors('veicolo_id');
    // Un veicolo di un altro cliente non va bene.
    $this->post('/pratiche', ['cliente_id' => $bianchi->id, 'veicolo_id' => $this->auto->id])->assertSessionHasErrors('veicolo_id');

    $this->post('/pratiche', ['cliente_id' => $this->rossi->id, 'veicolo_id' => $this->auto->id])->assertRedirect();
    $pratica = Pratica::first();
    expect($pratica->veicolo_id)->toBe($this->auto->id);
    $this->get("/pratiche/{$pratica->id}")->assertOk()->assertInertia(fn ($page) => $page->where('pratica.veicolo.targa', 'GT066RR'));
});

test('senza la funzione veicoli il veicolo è facoltativo', function () {
    $this->actingAs($this->user)->post('/pratiche', ['cliente_id' => $this->rossi->id])->assertRedirect();
    expect(Pratica::first()->veicolo_id)->toBeNull();
});

test('creazione rapida del cliente dal sinistro: gli errori tornano in JSON, il cliente creato si apre', function () {
    $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    $this->actingAs($this->user)->withHeaders($ajax)->post('/clienti', ['nome' => 'Nuovo', 'email' => 'sbagliata'])
        ->assertStatus(422)->assertJsonPath('errors.email.0', 'Inserisci un indirizzo email valido.');

    $id = $this->withHeaders($ajax)->post('/clienti', ['nome' => 'Nuovo'])->assertCreated()->json('cliente.id');
    $this->getJson("/clienti/{$id}/scheda")->assertOk()->assertJsonPath('cliente.nome', 'Nuovo')->assertJsonPath('veicoli', []);
});

test('dalla modifica cliente si vedono e si salvano tutti i dati anagrafici', function () {
    $this->actingAs($this->user)->get("/clienti/{$this->rossi->id}/edit")->assertOk()
        ->assertInertia(fn ($page) => $page->where('cliente.codice_esterno', '000001')->where('cliente.veicoli.0.targa', 'GT066RR'));

    $this->put("/clienti/{$this->rossi->id}", [
        'nome' => 'ROSSI MARIO', 'codice_fiscale' => 'rssmra80a01f704x', 'indirizzo' => 'Via Roma 1', 'cap' => '20900',
        'citta' => 'Monza', 'provincia' => 'mb', 'codice_esterno' => '000001',
    ])->assertRedirect();
    expect($this->rossi->fresh()->only(['codice_fiscale', 'citta', 'provincia', 'telefono']))
        ->toBe(['codice_fiscale' => 'RSSMRA80A01F704X', 'citta' => 'Monza', 'provincia' => 'MB', 'telefono' => '3331112222']);

    // Il codice del gestionale non può essere di due clienti.
    $bianchi = Cliente::where('nome', 'BIANCHI ANNA')->first();
    $this->put("/clienti/{$bianchi->id}", ['nome' => 'BIANCHI ANNA', 'codice_esterno' => '000001'])->assertSessionHasErrors('codice_esterno');
});

test('veicoli: si modificano e si eliminano, ma non se collegati a un sinistro', function () {
    $this->actingAs($this->user);
    $this->putJson("/veicoli/{$this->auto->id}", ['targa' => 'gt 066 rr', 'marca' => 'RENAULT', 'modello' => 'CLIO', 'km' => 12000, 'scadenza_revisione' => '2027-05-31'])
        ->assertOk()->assertJsonPath('veicolo.modello', 'CLIO')->assertJsonPath('veicolo.scadenza_revisione', '2027-05-31');
    expect($this->auto->fresh()->km)->toBe(12000);

    $this->putJson("/veicoli/{$this->auto->id}", ['targa' => '', 'telaio' => ''])->assertStatus(422);

    $altro = $this->rossi->veicoli()->create(['tenant_id' => $this->tenant->id, 'telaio' => 'VF1TELAIO']);
    $this->postJson("/clienti/{$this->rossi->id}/veicoli", ['telaio' => 'vf1telaio'])->assertStatus(422)->assertJsonPath('errors.telaio.0', 'Esiste già un veicolo con questo telaio.');

    Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $this->rossi->id, 'veicolo_id' => $this->auto->id,
        'current_status_id' => TenantStatus::first()->id]);
    $this->deleteJson("/veicoli/{$this->auto->id}")->assertStatus(422);
    $this->deleteJson("/veicoli/{$altro->id}")->assertOk();
    expect(Veicolo::count())->toBe(1);

    $estraneo = Veicolo::create(['tenant_id' => Tenant::create(['name' => 'X'])->id, 'cliente_id' => Cliente::create(['tenant_id' => Tenant::latest('id')->first()->id, 'nome' => 'Y'])->id, 'targa' => 'ZZ999ZZ']);
    $this->deleteJson("/veicoli/{$estraneo->id}")->assertNotFound();
});

test('nuovo cliente dal sinistro con tutti i dati, e il doppione si trova per partita IVA', function () {
    $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    $id = $this->actingAs($this->user)->withHeaders($ajax)->post('/clienti', [
        'nome' => 'Rossi S.r.l.', 'telefono' => '0396013000', 'email' => 'info@rossi.it', 'codice_fiscale' => '02958510964',
        'partita_iva' => '02958510964', 'indirizzo' => 'Viale Monterosa 102', 'cap' => '20862', 'citta' => 'Arcore', 'provincia' => 'mb',
    ])->assertCreated()->json('cliente.id');

    expect(Cliente::find($id)->only(['partita_iva', 'citta', 'provincia']))->toBe(['partita_iva' => '02958510964', 'citta' => 'Arcore', 'provincia' => 'MB']);
    expect($this->getJson('/clienti/cerca?q=02958510964')->json('risultati.0.id'))->toBe($id);
});

test('modificando il cliente dal sinistro si torna al sinistro, mai a un indirizzo esterno', function () {
    $this->actingAs($this->user);
    $this->get("/clienti/{$this->rossi->id}/edit?ritorno=/pratiche/5")->assertInertia(fn ($page) => $page->where('ritorno', '/pratiche/5'));
    $this->get("/clienti/{$this->rossi->id}/edit?ritorno=https://evil.example")->assertInertia(fn ($page) => $page->where('ritorno', null));

    $this->put("/clienti/{$this->rossi->id}", ['nome' => 'ROSSI MARIO', 'ritorno' => '/pratiche/5'])->assertRedirect('/pratiche/5');
    $this->put("/clienti/{$this->rossi->id}", ['nome' => 'ROSSI MARIO', 'ritorno' => '//evil.example/x'])->assertRedirect("/clienti/{$this->rossi->id}");
    $this->put("/clienti/{$this->rossi->id}", ['nome' => 'ROSSI MARIO'])->assertRedirect("/clienti/{$this->rossi->id}");
});

test('board: si apre un sinistro senza lasciarla e la scheda mostra cliente e veicolo', function () {
    $this->actingAs($this->user)->post('/pratiche', ['cliente_id' => $this->rossi->id, 'veicolo_id' => $this->auto->id, 'da' => 'kanban'])
        ->assertRedirect('/pratiche/kanban')->assertSessionHas('nuova_pratica_id');
    $id = Pratica::first()->id;

    $this->get('/pratiche/kanban')->assertOk()->assertInertia(fn ($page) => $page
        ->where('pratiche.0.veicolo.targa', 'GT066RR')
        ->where('chiediVeicolo', false)
        ->has('compagnie'));

    // Dalla pagina Nuovo sinistro si continua ad aprire il sinistro creato.
    $this->post('/pratiche', ['cliente_id' => $this->rossi->id])->assertRedirect('/pratiche/' . ($id + 1));
});

test('il conducente si segna sul sinistro, il cliente resta il proprietario', function () {
    $azienda = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'RCI BANQUE', 'partita_iva' => '12345678901']);

    $this->actingAs($this->user)->post(route('pratiche.store'), [
        'cliente_id' => $azienda->id, 'conducente_nome' => '  Verdi Luca ', 'conducente_telefono' => '3331234567',
    ])->assertRedirect();

    expect(\App\Models\Pratica::first()->only(['cliente_id', 'conducente_nome', 'conducente_telefono']))
        ->toBe(['cliente_id' => $azienda->id, 'conducente_nome' => 'Verdi Luca', 'conducente_telefono' => '3331234567']);
});
