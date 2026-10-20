<?php

use App\Models\{Allegato, Cliente, FieldDictionaryEntry, ModuleTemplate, Pratica, Tenant, TenantStatus, User};
use App\Services\PdfFormFillerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    Storage::fake('s3');
    $this->tenant = Tenant::create(['name' => 'Malacrida']);
    TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
});

/** PDF vuoto di $pagine pagine A4, come matrice di prova. */
function matriceDiProva(int $pagine): string
{
    $pdf = new Fpdi('P', 'mm', 'A4');
    for ($i = 0; $i < $pagine; $i++) {
        $pdf->AddPage();
    }
    return $pdf->Output('S');
}

/** Testo scritto nel PDF (flussi decompressi). */
function testoPdf(string $pdf): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m);
    return implode("\n", array_map(fn ($s) => @gzuncompress($s) ?: $s, $m[1]));
}

test('le date escono sempre all\'italiana, le caselle con la X e i Sì/No in chiaro', function () {
    $f = app(PdfFormFillerService::class);

    expect($f->formatValue('date', '2026-07-24'))->toBe('24/07/2026')
        ->and($f->formatValue('text', '2026-07-24T00:00:00.000000Z'))->toBe('24/07/2026')
        ->and($f->formatValue('date', '24/07/2026'))->toBe('24/07/2026')
        ->and($f->formatValue('checkbox', 'true'))->toBe('X')
        ->and($f->formatValue('checkbox', ''))->toBe('')
        ->and($f->formatValue('checkbox', 'false'))->toBe('')
        ->and($f->formatValue('boolean', 'true'))->toBe('Sì')
        ->and($f->formatValue('boolean', 'false'))->toBe('No')
        ->and($f->formatValue('text', ' Arcore '))->toBe('Arcore');
});

test('il PDF compilato ha la data gg/mm/aaaa e la X nella casella spuntata', function () {
    Storage::disk('s3')->put('pdf_templates/denuncia.pdf', matriceDiProva(1));
    $template = ModuleTemplate::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Denuncia',
        'pdf_template_s3_key' => 'pdf_templates/denuncia.pdf',
        'fields_schema' => [
            ['name' => 'data_sinistro', 'label' => 'Data sinistro', 'type' => 'date', 'required' => false, 'page' => 1, 'x' => 10, 'y' => 10, 'w' => 20],
            ['name' => 'kasko', 'label' => 'Sinistro kasko', 'type' => 'checkbox', 'required' => false, 'page' => 1, 'x' => 10, 'y' => 20, 'w' => 2],
            ['name' => 'rct', 'label' => 'Sinistro RCT', 'type' => 'checkbox', 'required' => false, 'page' => 1, 'x' => 10, 'y' => 25, 'w' => 2],
        ],
    ]);
    $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'ROSSI MARIO']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'utente_creatore_id' => $this->user->id]);

    $this->actingAs($this->user)->postJson("/pratiche/{$pratica->id}/modules", [
        'module_template_id' => $template->id,
        'values' => ['data_sinistro' => '2026-07-24', 'kasko' => 'true', 'rct' => ''],
    ])->assertCreated();

    $testo = testoPdf(Storage::disk('s3')->get(Allegato::firstOrFail()->s3_key));
    expect($testo)->toContain('(24/07/2026)')->not->toContain('2026-07-24')
        ->and(substr_count($testo, '(X)'))->toBe(1);
});

test('il superadmin sostituisce solo il PDF di un modulo: i campi restano, si tolgono quelli su pagine non più presenti', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $file = UploadedFile::fake()->createWithContent('giustificativo.pdf', matriceDiProva(2));

    $risposta = $this->actingAs($admin)->post(
        "/superadmin/tenants/{$this->tenant->id}/module-templates/extract-fields",
        ['pdf_file' => $file, 'solo_pdf' => '1'],
        ['Accept' => 'application/json'],
    )->assertOk();

    expect($risposta->json('pages'))->toBe(2)
        ->and($risposta->json('fields'))->toBeNull();
    Storage::disk('s3')->assertExists($risposta->json('s3_key'));
});

test('il dizionario accetta le caselle e i dati di residenza del cliente come sorgente', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($admin)->post("/superadmin/tenants/{$this->tenant->id}/field-dictionary/bulk", [
        'fields' => [
            ['key' => 'indirizzo_residenza', 'label' => 'Indirizzo di residenza', 'type' => 'text', 'source_type' => 'cliente', 'source_field' => 'indirizzo'],
            ['key' => 'sinistro_kasko', 'label' => 'Sinistro kasko', 'type' => 'checkbox', 'source_type' => 'manual', 'source_field' => null],
        ],
    ], ['Accept' => 'application/json'])->assertSuccessful();

    expect(FieldDictionaryEntry::where('tenant_id', $this->tenant->id)->pluck('source_field', 'key')->all())
        ->toBe(['indirizzo_residenza' => 'indirizzo', 'sinistro_kasko' => null]);
});

test('la tipologia è una scelta singola: un solo campo, la X va solo sulla casella scelta', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    Storage::disk('s3')->put('pdf_templates/denuncia.pdf', matriceDiProva(2));
    $tipologia = [
        'name' => 'tipologia_sinistro', 'label' => 'Tipologia di sinistro', 'type' => 'radio', 'required' => false,
        'page' => 1, 'x' => 8, 'y' => 58, 'w' => 2.4,
        'options' => ['R.C.T.', 'Kasko', 'Altro'],
        'positions' => [
            'R.C.T.' => ['page' => 1, 'x' => 8, 'y' => 58, 'w' => 2.4],
            'Kasko' => ['page' => 1, 'x' => 8, 'y' => 63, 'w' => 2.4],
            'Altro' => ['page' => 2, 'x' => 8, 'y' => 20, 'w' => 2.4],
        ],
    ];

    $this->actingAs($admin)->post("/superadmin/tenants/{$this->tenant->id}/module-templates", [
        'name' => 'Denuncia', 'pdf_template_s3_key' => 'pdf_templates/denuncia.pdf', 'fields_schema' => [$tipologia],
    ])->assertRedirect();
    $template = ModuleTemplate::firstOrFail();
    expect($template->fields_schema[0]['positions']['Kasko']['y'])->toBe(63);

    $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'ROSSI MARIO']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'utente_creatore_id' => $this->user->id]);

    $this->actingAs($this->user)->postJson("/pratiche/{$pratica->id}/modules", [
        'module_template_id' => $template->id, 'values' => ['tipologia_sinistro' => 'Altro'],
    ])->assertCreated();

    $pdf = Storage::disk('s3')->get(Allegato::firstOrFail()->s3_key);
    // Una sola X, sulla pagina 2 (dove sta "Altro"), e il nome dell'opzione non viene stampato.
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m);
    $pagine = array_values(array_filter(array_map(fn ($s) => @gzuncompress($s) ?: $s, $m[1]), fn ($t) => str_contains($t, 'BT')));
    expect(substr_count(implode('', $pagine), '(X)'))->toBe(1)
        ->and(testoPdf($pdf))->not->toContain('(Altro)');
});

test('nei messaggi automatici e nel fascicolo le date dei campi sono all\'italiana', function () {
    expect(App\Support\Formato::valore('2026-10-12'))->toBe('12/10/2026')
        ->and(App\Support\Formato::valore('2026-10-12T15:30'))->toBe('12/10/2026 15:30')
        ->and(App\Support\Formato::valore('2026-10-12 00:00:00'))->toBe('12/10/2026')
        ->and(App\Support\Formato::valore('2026-02-30'))->toBe('2026-02-30') // non è una data vera: resta com'è
        ->and(App\Support\Formato::valore('GT066RR'))->toBe('GT066RR')
        ->and(App\Support\Formato::valore(true))->toBe('Sì');

    $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'ROSSI MARIO']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'utente_creatore_id' => $this->user->id,
        'custom_fields' => ['data_sinistro' => '2026-10-12', 'targa_veicolo' => 'GT066RR']]);
    $testo = app(App\Services\AutomationPlanner::class)
        ->compilePratica('Sinistro del {campi_custom.data_sinistro}, auto {campi_custom.targa_veicolo}', $pratica, 'Mario');
    expect($testo)->toBe('Sinistro del 12/10/2026, auto GT066RR');
});
