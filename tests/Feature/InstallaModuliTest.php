<?php

use App\Models\{FieldDictionaryEntry, ModuleTemplate, Tenant};
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    Storage::fake('s3');
    $this->tenant = Tenant::create(['name' => 'Malacrida', 'settings' => ['custom_fields_schema' => [
        ['name' => 'numero_sinistro_compagnia', 'label' => 'Numero sinistro', 'type' => 'text'],
        ['name' => 'data_prima_immatricolazioe', 'label' => 'Data prima immatricolazione', 'type' => 'date'],
    ]]]);
    foreach (['Modulo denuncia sinistro', 'Dichiarazione scoperti assicurativi', 'Modulo cessione credito', 'Modulo stato necessità auto'] as $nome) {
        ModuleTemplate::create(['tenant_id' => $this->tenant->id, 'name' => $nome, 'pdf_template_s3_key' => 'vecchio.pdf', 'output_document_category_id' => null,
            'fields_schema' => [['name' => 'nome_cognome', 'label' => 'Nome', 'type' => 'text', 'page' => 1, 'x' => 1, 'y' => 1, 'w' => 10]]]);
    }
    FieldDictionaryEntry::create(['tenant_id' => $this->tenant->id, 'key' => 'telefono', 'label' => 'Tel', 'type' => 'text', 'source_type' => 'manual']);

    // Cartella come quella che si copia sul server: NOME.pdf + NOME.json per ogni modulo.
    $this->dir = sys_get_temp_dir() . '/moduli-test-' . uniqid();
    mkdir($this->dir);
    $campo = fn ($name, $label, $type = 'text', $page = 1) => compact('name', 'label', 'type', 'page') + ['required' => false, 'x' => 10, 'y' => 10, 'w' => 30];
    $schemi = [
        'MODULO_DENUNCIA_SINISTRO_MALACRIDA' => [$campo('cognome_nome', 'Cognome e nome'), $campo('telefono', 'Telefono'), $campo('numero_sinistro', 'Numero sinistro'),
            $campo('data_prima_immatricolazione', 'Data prima immatricolazione', 'date'),
            $campo('tipologia_sinistro', 'Tipologia di sinistro', 'radio') + ['options' => ['Kasko'], 'positions' => ['Kasko' => ['page' => 2, 'x' => 8, 'y' => 60, 'w' => 2]]]],
        'DICHIARAZIONE_SCOPERTI_ASSICURATIVI_MALACRIDA' => [$campo('cognome_nome', 'Cognome e nome'), $campo('garanzia_copertura', 'Garanzia / Copertura')],
        'MODULO_CESSIONE_CREDITO_MALACRIDA' => [$campo('cognome_nome', 'Cognome e nome'), $campo('data_compilazione', 'Data compilazione', 'date', 2)],
        'GIUSTIFICATIVO_NOLEGGIO_MALACRIDA' => [$campo('cognome_nome', 'Cognome e nome'), $campo('motivo_lavoro', 'Lavoro', 'checkbox')],
    ];
    foreach ($schemi as $base => $schema) {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->AddPage();
        file_put_contents("{$this->dir}/{$base}.pdf", $pdf->Output('S'));
        file_put_contents("{$this->dir}/{$base}.json", json_encode($schema));
    }
});

afterEach(function () {
    array_map('unlink', glob($this->dir . '/*'));
    rmdir($this->dir);
    array_map('unlink', glob(storage_path('app/backup-moduli-tenant' . $this->tenant->id . '-*.json')));
});

test('la simulazione mostra il piano e non cambia niente', function () {
    $this->artisan('app:installa-moduli', ['cartella' => $this->dir, '--tenant' => $this->tenant->id, '--dry-run' => true])
        ->expectsOutputToContain('Modulo stato necessità auto')
        ->expectsOutputToContain('[SIMULAZIONE] Niente è stato modificato.')
        ->assertSuccessful();

    expect(ModuleTemplate::pluck('pdf_template_s3_key')->unique()->all())->toBe(['vecchio.pdf'])
        ->and(FieldDictionaryEntry::count())->toBe(1);
});

test('carica i 4 PDF sui modelli giusti, con i campi, il dizionario e il backup', function () {
    $this->artisan('app:installa-moduli', ['cartella' => $this->dir, '--tenant' => $this->tenant->id])->assertSuccessful();

    $denuncia = ModuleTemplate::where('name', 'Modulo denuncia sinistro')->first();
    expect($denuncia->pdf_template_s3_key)->not->toBe('vecchio.pdf')
        ->and(array_column($denuncia->fields_schema, 'name'))->toBe(['cognome_nome', 'telefono', 'numero_sinistro', 'data_prima_immatricolazione', 'tipologia_sinistro'])
        ->and($denuncia->fields_schema[4]['positions']['Kasko']['page'])->toBe(2);
    Storage::disk('s3')->assertExists($denuncia->pdf_template_s3_key);
    expect(array_column(ModuleTemplate::where('name', 'Modulo stato necessità auto')->first()->fields_schema, 'name'))->toBe(['cognome_nome', 'motivo_lavoro']);

    $diz = FieldDictionaryEntry::get()->keyBy('key');
    expect($diz['cognome_nome']->only(['source_type', 'source_field']))->toBe(['source_type' => 'cliente', 'source_field' => 'nome'])
        ->and($diz['telefono']->only(['source_type', 'source_field']))->toBe(['source_type' => 'cliente', 'source_field' => 'telefono']) // voce esistente aggiornata
        ->and($diz['numero_sinistro']->only(['source_type', 'source_field']))->toBe(['source_type' => 'pratica_field', 'source_field' => 'numero_sinistro_compagnia'])
        ->and($diz['data_prima_immatricolazione']->source_field)->toBe('data_prima_immatricolazioe') // stessa etichetta, nome con refuso
        ->and($diz['tipologia_sinistro']->only(['type', 'source_type']))->toBe(['type' => 'radio', 'source_type' => 'manual'])
        ->and($diz)->toHaveCount(8);

    $backup = glob(storage_path('app/backup-moduli-tenant' . $this->tenant->id . '-*.json'));
    expect($backup)->toHaveCount(1)
        ->and(json_decode(file_get_contents($backup[0]), true)['modelli'][0]['pdf_template_s3_key'])->toBe('vecchio.pdf');
});

test('se un modello non si abbina in modo sicuro non tocca niente', function () {
    ModuleTemplate::create(['tenant_id' => $this->tenant->id, 'name' => 'Altra denuncia', 'fields_schema' => []]);

    $this->artisan('app:installa-moduli', ['cartella' => $this->dir, '--tenant' => $this->tenant->id])
        ->expectsOutputToContain('trovati 2')
        ->assertFailed();

    expect(ModuleTemplate::whereNotNull('pdf_template_s3_key')->pluck('pdf_template_s3_key')->unique()->all())->toBe(['vecchio.pdf']);
});
