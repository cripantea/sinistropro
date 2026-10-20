<?php

use App\Models\{Cliente, Importazione, Tenant, User, Veicolo};
use App\Jobs\ImportaClientiJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

beforeEach(function () {
    Storage::fake('local');
    $this->tenant = Tenant::create(['name' => 'Malacrida']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
});

/** Excel "una riga per veicolo", come l'export del gestionale di Malacrida. */
function excelClienti(array $righe): UploadedFile
{
    $sheet = new Spreadsheet();
    $sheet->getActiveSheet()->fromArray(
        [['Cod. Cliente', 'Cognome', 'Tel. Cellulare', 'eMail', 'Codice Fiscale', 'Telaio', 'Marca', 'Targa', 'Data Imm.'], ...$righe],
        null, 'A1', true
    );
    $path = tempnam(sys_get_temp_dir(), 'imp') . '.xls';
    (new Xls($sheet))->save($path);

    return new UploadedFile($path, 'elenco clienti.xls', null, null, true);
}

function importa($test, UploadedFile $file, bool $aggiorna = true, array $abbinamenti = []): array
{
    $preview = $test->actingAs($test->user)->post('/clienti/import/preview', ['file' => $file])->assertOk()->json();
    $id = $preview['importazione_id'];
    $body = ['mapping' => $preview['mapping'], 'aggiorna_esistenti' => $aggiorna, 'abbinamenti' => $abbinamenti];
    $analisi = $test->postJson("/clienti/import/{$id}/analizza", $body)->assertOk()->json();
    $test->postJson("/clienti/import/{$id}/esegui", $body)->assertOk();

    return [$preview, $analisi, Importazione::find($id)];
}

test('excel una riga per veicolo: un cliente con più veicoli, mappatura proposta da sola', function () {
    [$preview, $analisi, $imp] = importa($this, excelClienti([
        ['000001', 'RCI BANQUE SA .', '3880481145', 'clienti@rci.com', '05574741004', 'JSAJTD44V00631271', 'SUZUKI', 'EL041BW', 40939],
        ['000001', 'RCI BANQUE SA .', '3880481145', 'clienti@rci.com', '05574741004', 'VF1RJK00972650958', 'RENAULT', 'GT066RR', 45380],
        ['000002', 'VILLA ANGELO', '', 'NOMAIL', 'VLLNGL71A18M052W', '', '', '', ''],
    ]));

    expect($preview['mapping']['Cod. Cliente'])->toBe('codice_esterno')
        ->and($preview['mapping']['Targa'])->toBe('veicolo.targa');
    expect($analisi['riepilogo'])->toMatchArray(['clienti_nuovi' => 2, 'veicoli_nuovi' => 2]);
    expect(Cliente::count())->toBe(2); // l'analisi non scrive nulla, l'import sì (coda sync nei test)

    expect($imp->stato)->toBe('completata');
    $rci = Cliente::where('codice_esterno', '000001')->first();
    expect($rci->nome)->toBe('RCI BANQUE SA')
        ->and($rci->veicoli()->pluck('targa')->all())->toBe(['EL041BW', 'GT066RR'])
        ->and($rci->veicoli()->first()->data_immatricolazione->toDateString())->toBe('2012-01-31');
    expect(Cliente::where('codice_esterno', '000002')->first()->email)->toBeNull();
    expect($imp->fresh()->leggiRighe())->toBeNull(); // le righe del file non restano salvate
});

test('il file completo ricaricato aggiunge solo i nuovi e non tocca né toglie quelli presenti', function () {
    importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', '', '', 'TELAIO1', 'FIAT', 'AA111AA', ''],
        ['000002', 'BIANCHI ANNA', '', '', '', '', '', '', ''],
    ]));
    $rossi = Cliente::where('codice_esterno', '000001')->first();
    $rossi->update(['nome' => 'Mario Rossi (corretto a mano)']);
    $aMano = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Cliente a mano']);

    // Il nuovo export: Bianchi non c'è più, Rossi ha un'auto in più, arriva Verdi.
    [, $analisi] = importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', '', '', 'TELAIO1', 'FIAT', 'AA111AA', ''],
        ['000001', 'ROSSI MARIO', '3331112222', '', '', 'TELAIO2', 'DACIA', 'BB222BB', ''],
        ['000003', 'VERDI LUCA', '', '', '', '', '', '', ''],
    ]));

    expect($analisi['riepilogo'])->toMatchArray(['clienti_nuovi' => 1, 'clienti_esistenti' => 1, 'veicoli_nuovi' => 1, 'veicoli_esistenti' => 1]);
    expect(Cliente::count())->toBe(4);
    expect(Cliente::where('codice_esterno', '000002')->exists())->toBeTrue();
    expect($aMano->fresh())->not->toBeNull();
    expect($rossi->fresh()->nome)->toBe('Mario Rossi (corretto a mano)');
    expect($rossi->veicoli()->count())->toBe(2);
});

test('un cliente inserito a mano viene riconosciuto dal codice fiscale: si riempiono solo i suoi campi vuoti', function () {
    $aMano = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Galbiati D.', 'telefono' => '3471234567', 'codice_fiscale' => 'GLBDVD71L02F704W']);

    importa($this, excelClienti([['000005', 'GALBIATI DAVIDE', '3395726185', 'davide@example.com', 'GLBDVD71L02F704W', '', '', '', '']]));

    expect(Cliente::count())->toBe(1);
    $aMano->refresh();
    expect($aMano->codice_esterno)->toBe('000005')
        ->and($aMano->nome)->toBe('Galbiati D.')
        ->and($aMano->telefono)->toBe('3471234567')
        ->and($aMano->email)->toBe('davide@example.com');
});

test('senza aggiornamento i clienti esistenti restano identici', function () {
    importa($this, excelClienti([['000001', 'ROSSI MARIO', '', '', '', '', '', '', '']]));
    importa($this, excelClienti([['000001', 'ROSSI MARIO', '3331112222', 'mario@example.com', '', '', '', '', '']]), aggiorna: false);

    expect(Cliente::first()->only(['telefono', 'email']))->toBe(['telefono' => null, 'email' => null]);
});

test('si aggiorna solo ciò che è cambiato nel gestionale, mai le correzioni fatte a mano', function () {
    importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', 'vecchia@example.com', '', 'TELAIO1', 'FIAT', 'AA111AA', ''],
        ['000002', 'BIANCHI ANNA', '3335556666', '', '', '', '', '', ''],
    ]));
    // In SinistroPro qualcuno corregge il telefono di Bianchi.
    Cliente::where('codice_esterno', '000002')->first()->update(['telefono' => '3400000000']);
    $prima = Cliente::where('codice_esterno', '000001')->first()->updated_at;
    $this->travel(1)->minutes();

    // Nel gestionale cambiano l'email di Rossi e il telefono di Bianchi; la targa di Rossi viene corretta.
    [, $analisi] = importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', 'nuova@example.com', '', 'TELAIO1', 'FIAT', 'AA111AB', ''],
        ['000002', 'BIANCHI ANNA', '3339999999', '', '', '', '', '', ''],
    ]));

    expect($analisi['riepilogo'])->toMatchArray(['clienti_nuovi' => 0, 'clienti_aggiornati' => 1, 'veicoli_aggiornati' => 1]);
    expect($analisi['riepilogo']['esempi_modifiche'][0])->toMatchArray(['nome' => 'ROSSI MARIO']);
    expect(Cliente::where('codice_esterno', '000001')->first()->email)->toBe('nuova@example.com');
    expect(Cliente::where('codice_esterno', '000002')->first()->telefono)->toBe('3400000000');
    expect(Veicolo::where('telaio', 'TELAIO1')->first()->targa)->toBe('AA111AB');

    // Lo stesso file una seconda volta non scrive più niente.
    [, $analisi] = importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', 'nuova@example.com', '', 'TELAIO1', 'FIAT', 'AA111AB', ''],
        ['000002', 'BIANCHI ANNA', '3339999999', '', '', '', '', '', ''],
    ]));
    expect($analisi['riepilogo'])->toMatchArray(['clienti_aggiornati' => 0, 'veicoli_aggiornati' => 0]);
});

test('clienti inseriti a mano: abbinati uno per uno per telefono, con il riepilogo da controllare', function () {
    $mario = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'telefono' => '+39 333 111 2222']);
    $pratica = \App\Models\Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $mario->id,
        'current_status_id' => \App\Models\TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true])->id]);
    $diverso = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Studio Verdi', 'telefono' => '3335556666']);
    $nessuno = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Cliente sconosciuto', 'telefono' => '3470000000']);

    [, $analisi] = importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', '', '', '', '', '', ''],
        ['000002', 'BIANCHI ANNA', '3335556666', '', '', '', '', '', ''],
    ]));

    $stati = collect($analisi['riconciliazione'])->pluck('stato', 'nome');
    expect($stati->all())->toBe(['Studio Verdi' => 'da_controllare', 'Cliente sconosciuto' => 'nessuno', 'Mario Rossi' => 'abbinato']);
    expect(collect($analisi['riconciliazione'])->firstWhere('nome', 'Mario Rossi'))->toMatchArray(['criterio' => 'telefono', 'pratiche' => 1]);

    // Mario non si duplica e il suo sinistro resta collegato.
    expect($mario->fresh()->codice_esterno)->toBe('000001')->and($mario->fresh()->nome)->toBe('Mario Rossi');
    expect($pratica->fresh()->cliente_id)->toBe($mario->id);
    expect(Cliente::count())->toBe(3);
});

test('nella verifica un abbinamento si può togliere o correggere a mano', function () {
    $verdi = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Studio Verdi', 'telefono' => '3335556666']);
    $luca = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Luca']);

    importa($this, excelClienti([
        ['000002', 'BIANCHI ANNA', '3335556666', '', '', '', '', '', ''],
        ['000003', 'NERI LUCA', '', '', '', '', '', '', ''],
    ]), abbinamenti: [$verdi->id => '', $luca->id => '000003']);

    expect($verdi->fresh()->codice_esterno)->toBeNull();
    expect($luca->fresh()->codice_esterno)->toBe('000003');
    expect(Cliente::count())->toBe(3);
});

test('due clienti a mano sullo stesso cliente del file: nessun abbinamento automatico', function () {
    Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Rossi', 'codice_fiscale' => 'RSSMRA80A01F704X']);
    Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'telefono' => '3331112222']);

    [, $analisi] = importa($this, excelClienti([['000001', 'ROSSI MARIO', '3331112222', '', 'RSSMRA80A01F704X', '', '', '', '']]));

    expect(collect($analisi['riconciliazione'])->pluck('stato')->unique()->all())->toBe(['conflitto']);
    expect(Cliente::whereNotNull('codice_esterno')->count())->toBe(1)->and(Cliente::count())->toBe(3);
});

test('lo stesso codice fiscale su più clienti del file non li unisce', function () {
    Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Leasing', 'codice_fiscale' => '01000500171']);

    [, $analisi] = importa($this, excelClienti([
        ['001283', 'SBS LEASING SPA .', '', '', '01000500171', '', '', '', ''],
        ['001487', 'UBI LEASING S.P.A. .', '', '', '01000500171', '', '', '', ''],
    ]));

    expect(Cliente::count())->toBe(3);
    // ...ma li propone entrambi, da scegliere a mano.
    expect($analisi['riconciliazione'][0]['stato'])->toBe('piu_candidati')
        ->and(collect($analisi['riconciliazione'][0]['candidati'])->pluck('codice')->all())->toBe(['001283', '001487']);
});

test('json annidato: i veicoli dentro il cliente diventano veicoli del cliente', function () {
    $json = json_encode(['clienti' => [
        ['codice' => 'A1', 'nome' => 'Mario Rossi', 'contatti' => ['email' => 'm@example.com'], 'veicoli' => [['targa' => 'AA111AA'], ['targa' => 'BB222BB']]],
        ['codice' => 'A2', 'nome' => 'Anna Bianchi', 'veicoli' => []],
    ]]);
    $file = UploadedFile::fake()->createWithContent('clienti.json', $json);

    [$preview] = importa($this, $file);

    expect($preview['mapping'])->toMatchArray(['codice' => 'codice_esterno', 'veicoli.targa' => 'veicolo.targa']);
    expect(Cliente::where('codice_esterno', 'A1')->first()->veicoli()->count())->toBe(2);
    expect(Cliente::where('codice_esterno', 'A2')->exists())->toBeTrue();
});

test('csv con punto e virgola e codifica windows', function () {
    $csv = mb_convert_encoding("Codice;Nome;Città\n1;Niccolò Rossi;Monza\n", 'Windows-1252', 'UTF-8');
    importa($this, UploadedFile::fake()->createWithContent('clienti.csv', $csv));

    expect(Cliente::first()->only(['nome', 'citta']))->toBe(['nome' => 'Niccolò Rossi', 'citta' => 'Monza']);
});

test('la mappatura usata viene ricordata per il prossimo file', function () {
    $file = UploadedFile::fake()->createWithContent('c.csv', "Ragione,Rif\nRossi,X1\n");
    $preview = $this->actingAs($this->user)->post('/clienti/import/preview', ['file' => $file])->json();
    $this->postJson("/clienti/import/{$preview['importazione_id']}/esegui", ['mapping' => ['Ragione' => 'nome', 'Rif' => 'codice_esterno']])->assertOk();

    $again = $this->post('/clienti/import/preview', ['file' => UploadedFile::fake()->createWithContent('c.csv', "Ragione,Rif\nVerdi,X2\n")])->json();
    expect($again['mapping'])->toBe(['Ragione' => 'nome', 'Rif' => 'codice_esterno']);
});

test('una importazione non parte due volte e non è visibile da un altro tenant', function () {
    $preview = $this->actingAs($this->user)->post('/clienti/import/preview', ['file' => excelClienti([['1', 'ROSSI', '', '', '', '', '', '', '']])])->json();
    $id = $preview['importazione_id'];
    $this->postJson("/clienti/import/{$id}/esegui", ['mapping' => $preview['mapping']])->assertOk();
    $this->postJson("/clienti/import/{$id}/esegui", ['mapping' => $preview['mapping']])->assertStatus(422);
    expect(Cliente::count())->toBe(1);

    $altro = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Altro'])->id, 'role' => 'tenant-admin']);
    $this->actingAs($altro)->getJson("/clienti/import/{$id}")->assertNotFound();
});

test('senza colonna nome non si importa', function () {
    $preview = $this->actingAs($this->user)->post('/clienti/import/preview', ['file' => excelClienti([['1', 'ROSSI', '', '', '', '', '', '', '']])])->json();
    $mapping = array_map(fn ($t) => $t === 'nome' ? '' : $t, $preview['mapping']);

    $this->postJson("/clienti/import/{$preview['importazione_id']}/analizza", ['mapping' => $mapping])->assertStatus(422);
    $this->postJson("/clienti/import/{$preview['importazione_id']}/analizza", ['mapping' => ['Cognome' => 'tenant_id']])
        ->assertStatus(422)->assertJson(['error' => 'Campo di destinazione non valido.']);
});

test('in caso di conflitto, la scelta fatta a mano vince e l altro resta separato', function () {
    $a = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Rossi', 'codice_fiscale' => 'RSSMRA80A01F704X']);
    $b = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'telefono' => '3331112222']);

    [, $analisi] = importa($this, excelClienti([['000001', 'ROSSI MARIO', '3331112222', '', 'RSSMRA80A01F704X', '', '', '', '']]), abbinamenti: [$b->id => '000001']);

    expect(collect($analisi['riconciliazione'])->pluck('stato', 'nome')->all())->toBe(['Rossi' => 'nessuno', 'Mario Rossi' => 'abbinato']);
    expect($b->fresh()->codice_esterno)->toBe('000001')->and($a->fresh()->codice_esterno)->toBeNull();
});

test('auto vendute: se il telaio passa a un altro cliente l\'auto viene spostata, i sinistri restano', function () {
    importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', '', '', 'TELAIO1', 'FIAT', 'AA111AA', ''],
        ['000002', 'BIANCHI ANNA', '3335556666', '', '', '', '', '', ''],
    ]));
    $rossi = Cliente::where('codice_esterno', '000001')->first();
    $veicolo = Veicolo::where('telaio', 'TELAIO1')->first();
    $pratica = \App\Models\Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $rossi->id, 'veicolo_id' => $veicolo->id,
        'current_status_id' => \App\Models\TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true])->id]);

    // Rossi vende l'auto a Bianchi e a un cliente nuovo arriva un'altra auto già nota.
    [, $analisi] = importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', '', '', '', '', '', ''],
        ['000002', 'BIANCHI ANNA', '3335556666', '', '', 'TELAIO1', 'FIAT', 'AA111AA', ''],
    ]));

    expect($analisi['riepilogo'])->toMatchArray(['veicoli_spostati' => 1, 'veicoli_nuovi' => 0]);
    $bianchi = Cliente::where('codice_esterno', '000002')->first();
    expect(Veicolo::count())->toBe(1)
        ->and($veicolo->fresh()->cliente_id)->toBe($bianchi->id)
        ->and($pratica->fresh()->cliente_id)->toBe($rossi->id);

    // Reimportando lo stesso file non si sposta più niente.
    [, $analisi] = importa($this, excelClienti([
        ['000001', 'ROSSI MARIO', '3331112222', '', '', '', '', '', ''],
        ['000002', 'BIANCHI ANNA', '3335556666', '', '', 'TELAIO1', 'FIAT', 'AA111AA', ''],
    ]));
    expect($analisi['riepilogo']['veicoli_spostati'])->toBe(0);
});

test('se le righe del file non si leggono l\'import risulta interrotto, mai "completato con 0 clienti"', function () {
    $imp = Importazione::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'file_name' => 'clienti.xls',
        'file_id' => (string) Str::uuid(), 'stato' => 'in_coda', 'mapping' => ['nome' => 'Cognome'], 'opzioni' => [],
    ]);

    try {
        ImportaClientiJob::dispatch($imp->id);
    } catch (\Throwable) {
        // la coda sync dei test rilancia l'errore dopo aver chiamato failed()
    }

    expect($imp->fresh()->stato)->toBe('fallita')
        ->and($imp->fresh()->errore)->toContain('ricarica il file');
});
