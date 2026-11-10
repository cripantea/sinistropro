<?php

use App\Models\{EmailFirma, EmailMessage, EmailThread, Tenant, User};
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;
use Inertia\Testing\AssertableInertia as Assert;

// Firme email: lista personale + di agenzia, predefinita, scelta all'invio.

beforeEach(function () {
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void {}
    });
    $this->tenant = Tenant::create(['name' => 'Agenzia Firme']);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->collega = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
    $altro = Tenant::create(['name' => 'Altra']);
    $this->estranea = EmailFirma::create(['tenant_id' => $altro->id, 'user_id' => null, 'nome' => 'Altra', 'testo' => 'NON MIA']);
});

test('ognuno ha le sue firme, quelle di agenzia le crea l\'amministratore e le vedono tutti', function () {
    $this->actingAs($this->admin)->postJson('/email/firme', ['nome' => 'Ufficio sinistri', 'testo' => "Ufficio sinistri\nTel. 02 123", 'agenzia' => true, 'preferita' => true])
        ->assertOk()->assertJsonPath('firme.0.agenzia', true)->assertJsonPath('preferita', fn ($id) => $id > 0);

    $this->actingAs($this->collega)->postJson('/email/firme', ['nome' => 'Tutti', 'testo' => 'x', 'agenzia' => true])->assertForbidden();
    $this->postJson('/email/firme', ['nome' => '', 'testo' => ''])->assertJsonValidationErrors(['nome', 'testo']);
    $this->postJson('/email/firme', ['nome' => 'Mario', 'testo' => 'Mario Rossi', 'preferita' => true])->assertOk()->assertJsonCount(2, 'firme');

    $agenzia = EmailFirma::whereNull('user_id')->where('tenant_id', $this->tenant->id)->sole();
    $mia = EmailFirma::where('user_id', $this->collega->id)->sole();

    // Il collega non tocca la firma di agenzia; e nemmeno l'amministratore cancella quella personale del collega.
    $this->putJson("/email/firme/{$agenzia->id}", ['nome' => 'X', 'testo' => 'X'])->assertForbidden();
    $this->deleteJson("/email/firme/{$agenzia->id}")->assertForbidden();
    $this->get('/email')->assertInertia(fn (Assert $p) => $p->has('firme.firme', 2)->where('firme.preferita', $mia->id));
    $this->actingAs($this->admin)->deleteJson("/email/firme/{$mia->id}")->assertForbidden();
    $this->deleteJson("/email/firme/{$this->estranea->id}")->assertNotFound();

    $this->actingAs($this->collega)->postJson('/email/firme/preferita', ['firma_id' => $agenzia->id])->assertJsonPath('preferita', $agenzia->id);
    $this->postJson('/email/firme/preferita', ['firma_id' => $this->estranea->id])->assertNotFound();
    $this->deleteJson("/email/firme/{$mia->id}")->assertOk()->assertJsonCount(1, 'firme');
});

test('la firma scelta va in fondo all\'email, nuova o risposta; nessuna firma = testo com\'è', function () {
    $firma = EmailFirma::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->collega->id, 'nome' => 'Mario', 'testo' => "Mario Rossi\nAgenzia <Firme>"]);

    $this->actingAs($this->collega)->postJson('/email/compose', ['to' => 'cliente@example.com', 'subject' => 'Ciao', 'body' => 'Testo', 'firma_id' => $firma->id])->assertOk();
    expect(EmailMessage::latest('id')->first()->body_html)->toBe('Testo<br><br>Mario Rossi<br />'."\n".'Agenzia &lt;Firme&gt;');

    $thread = EmailThread::where('counterpart_email', 'cliente@example.com')->sole();
    $this->postJson("/email/threads/{$thread->id}/reply", ['body' => 'Risposta'])->assertOk();
    expect(EmailMessage::latest('id')->first()->body_html)->toBe('Risposta');

    $this->postJson("/email/threads/{$thread->id}/reply", ['body' => 'Altra', 'firma_id' => $this->estranea->id])->assertStatus(422);
});

test('firma con immagine: nell\'email è incorporata, in SinistroPro si vede; la predefinita per tutti la propone a chi non ha scelto', function () {
    \Illuminate\Support\Facades\Storage::fake('s3');
    $png = \Illuminate\Http\UploadedFile::fake()->image('FIRMA.png', 300, 120);

    $this->actingAs($this->admin)->post('/email/firme', ['nome' => 'Generale', 'testo' => '', 'agenzia' => '1', 'immagine' => $png])->assertOk();
    $firma = EmailFirma::where('nome', 'Generale')->sole();
    expect($firma->immagine)->not->toBeNull();
    \Illuminate\Support\Facades\Storage::disk('s3')->assertExists($firma->immagine);

    $this->postJson('/email/firme/predefinita', ['firma_id' => $firma->id])->assertJsonPath('predefinitaAgenzia', $firma->id);
    $this->actingAs($this->collega)->postJson('/email/firme/predefinita', ['firma_id' => null])->assertForbidden();
    $this->get('/email')->assertInertia(fn (Assert $p) => $p->where('firme.preferita', $firma->id));
    $this->get("/email/firme/{$firma->id}/immagine")->assertOk()->assertHeader('Content-Type', 'image/png');

    $inviate = new ArrayObject();
    app()->bind(TenantMailerResolver::class, fn () => new class($inviate) extends TenantMailerResolver {
        public function __construct(public ArrayObject $inviate) {}
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void { $this->inviate[] = $mailable; }
    });
    $this->postJson('/email/compose', ['to' => 'cliente@example.com', 'subject' => 'Ciao', 'body' => 'Testo', 'firma_id' => $firma->id])->assertOk();

    expect($inviate[0]->bodyHtml)->toContain('src="cid:'.$firma->cid().'"')
        ->and($inviate[0]->inline[0]['cid'])->toBe($firma->cid())
        ->and(EmailMessage::latest('id')->first()->body_html)->toContain('/email/firme/'.$firma->id.'/immagine');

    // Le email automatiche dell'agenzia usano la firma predefinita.
    expect(EmailFirma::predefinitaPer($this->tenant->fresh())?->id)->toBe($firma->id);
});
