<?php

use App\Jobs\SyncTenantMailboxJob;
use App\Models\{Tenant, TenantMailSettings, User};
use App\Services\{EmailConnectionTester, EmailProviders};
use Illuminate\Support\Facades\{Bus, Http};
use Inertia\Testing\AssertableInertia as Assert;

// L'agenzia collega da sola la casella: provider riconosciuto dall'indirizzo, prova prima di salvare.

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Agenzia Ponte']);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->utente = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);

    $this->tester = Mockery::mock(EmailConnectionTester::class);
    $this->app->instance(EmailConnectionTester::class, $this->tester);
    Bus::fake();
});

function providers(array $mx = []): EmailProviders
{
    $p = Mockery::mock(EmailProviders::class)->makePartial();
    $p->shouldReceive('mxHosts')->andReturn($mx);

    return $p;
}

test('Gmail, Libero e Outlook si riconoscono dal dominio', function () {
    $gmail = providers()->detect('Mario.Rossi@gmail.com');
    expect($gmail['chiave'])->toBe('gmail')
        ->and($gmail['imap']['host'])->toBe('imap.gmail.com')
        ->and($gmail['username'])->toBe('mario.rossi@gmail.com')
        ->and($gmail['password_app'])->toBeTrue()
        ->and($gmail['link'])->toContain('apppasswords');

    expect(providers()->detect('a@libero.it')['smtp']['host'])->toBe('smtp.libero.it');

    $outlook = providers()->detect('a@hotmail.it');
    expect($outlook['chiave'])->toBe('microsoft')->and($outlook['supportato'])->toBeFalse();
});

test('un dominio proprio si riconosce dall\'MX (Google Workspace, Aruba) o dal database di Thunderbird', function () {
    expect(providers(['aspmx.l.google.com'])->detect('sinistri@assiponte.it'))
        ->nome->toBe('Google Workspace')
        ->chiave->toBe('gmail');

    expect(providers(['mx.aruba.it'])->detect('info@studio.it')['imap']['host'])->toBe('imaps.aruba.it');

    Http::fake(['autoconfig.thunderbird.net/*' => Http::response(<<<'XML'
        <clientConfig version="1.1"><emailProvider id="esempio.it">
          <displayName>Esempio Mail</displayName>
          <incomingServer type="imap"><hostname>imap.esempio.it</hostname><port>143</port><socketType>plain</socketType><username>%EMAILADDRESS%</username></incomingServer>
          <incomingServer type="imap"><hostname>imap.esempio.it</hostname><port>993</port><socketType>SSL</socketType><username>%EMAILLOCALPART%</username></incomingServer>
          <outgoingServer type="smtp"><hostname>smtp.esempio.it</hostname><port>587</port><socketType>STARTTLS</socketType><username>%EMAILADDRESS%</username></outgoingServer>
        </emailProvider></clientConfig>
        XML)]);

    $p = providers(['mx1.esempio.it'])->detect('mario@esempio.it');
    expect($p['imap'])->toBe(['host' => 'imap.esempio.it', 'port' => 993, 'encryption' => 'ssl'])
        ->and($p['smtp'])->toBe(['host' => 'smtp.esempio.it', 'port' => 587, 'encryption' => 'tls'])
        ->and($p['username'])->toBe('mario');
});

test('l\'admin collega Gmail con email e password per le app: si prova, si salva e parte la sincronizzazione', function () {
    $this->tester->shouldReceive('test')->once()
        ->withArgs(fn ($c, $prov) => $c['imap_host'] === 'imap.gmail.com' && $c['password'] === 'abcdefghijklmnop' && $prov === 'gmail')
        ->andReturn(null);

    $this->actingAs($this->admin)
        ->post('/impostazioni/email', ['email' => 'sinistri.ponte@gmail.com', 'password' => 'abcd efgh ijkl mnop', 'nome' => ''])
        ->assertRedirect('/impostazioni/email')
        ->assertSessionHasNoErrors();

    $s = TenantMailSettings::where('tenant_id', $this->tenant->id)->first();
    expect($s->is_active)->toBeTrue()
        ->and($s->host)->toBe('smtp.gmail.com')
        ->and($s->from_address)->toBe('sinistri.ponte@gmail.com')
        ->and($s->from_name)->toBe('Agenzia Ponte')
        ->and($s->password)->toBe('abcdefghijklmnop');
    Bus::assertDispatched(SyncTenantMailboxJob::class);

    $this->get('/impostazioni/email')->assertInertia(fn (Assert $page) => $page
        ->component('Impostazioni/Email')
        ->where('collegata.email', 'sinistri.ponte@gmail.com')
        ->missing('collegata.password'));
});

test('se il login non va, l\'errore resta sul campo password e non si salva nulla', function () {
    $this->tester->shouldReceive('test')->once()->andReturn('Email o password non accettate.');

    $this->actingAs($this->admin)
        ->post('/impostazioni/email', ['email' => 'x@gmail.com', 'password' => 'sbagliata'])
        ->assertSessionHasErrors(['password' => 'Email o password non accettate.']);

    expect(TenantMailSettings::count())->toBe(0);
});

test('Outlook viene fermato con la spiegazione, senza provare il collegamento', function () {
    $this->tester->shouldNotReceive('test');

    $this->actingAs($this->admin)
        ->post('/impostazioni/email', ['email' => 'x@outlook.it', 'password' => 'qualcosa'])
        ->assertSessionHasErrors('email');
});

test('cambiare casella riparte da zero con la sincronizzazione; scollegare toglie la password', function () {
    TenantMailSettings::create([
        'tenant_id' => $this->tenant->id, 'from_address' => 'vecchia@libero.it', 'host' => 'smtp.libero.it', 'port' => 465,
        'imap_host' => 'imapmail.libero.it', 'password' => 'vecchia', 'is_active' => true,
        'imap_last_uid_inbox' => 500, 'imap_last_uid_sent' => 80,
    ]);
    $this->tester->shouldReceive('test')->andReturn(null);

    // Stessa casella senza password: tiene quella salvata e non azzera gli UID.
    $this->actingAs($this->admin)->post('/impostazioni/email', ['email' => 'vecchia@libero.it', 'password' => '', 'nome' => 'Ponte'])->assertSessionHasNoErrors();
    $s = TenantMailSettings::first();
    expect($s->password)->toBe('vecchia')->and((int) $s->imap_last_uid_inbox)->toBe(500);

    $this->post('/impostazioni/email', ['email' => 'nuova@gmail.com', 'password' => 'pw'])->assertSessionHasNoErrors();
    $s->refresh();
    expect($s->imap_last_uid_inbox)->toBeNull()->and($s->imap_last_uid_sent)->toBeNull();

    $this->delete('/impostazioni/email')->assertRedirect('/impostazioni/email');
    $s->refresh();
    expect($s->is_active)->toBeFalse()->and($s->password)->toBeNull();
});

test('un collaboratore non può collegare la casella', function () {
    $this->actingAs($this->utente)->get('/impostazioni/email')->assertForbidden();
    $this->actingAs($this->utente)->post('/impostazioni/email', ['email' => 'x@gmail.com', 'password' => 'p'])->assertForbidden();
});

test('il superadmin dentro l\'agenzia può collegarla', function () {
    $sa = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);
    $this->tester->shouldReceive('test')->andReturn(null);

    $this->actingAs($sa)->withSession(['sa_tenant_id' => $this->tenant->id])
        ->post('/impostazioni/email', ['email' => 'x@gmail.com', 'password' => 'pw'])
        ->assertSessionHasNoErrors();

    expect(TenantMailSettings::where('tenant_id', $this->tenant->id)->value('from_address'))->toBe('x@gmail.com');
});

// ── Microsoft (Outlook / Microsoft 365) con OAuth ─────────────────────────────

function idToken(array $claims): string
{
    $b64 = fn ($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');

    return $b64(['alg' => 'none']).'.'.$b64($claims).'.firma';
}

test('Microsoft: il pulsante porta al login Microsoft e al ritorno la casella si salva con i token', function () {
    config(['services.microsoft_mail.client_id' => 'cid', 'services.microsoft_mail.client_secret' => 'sec']);
    Http::fake(['login.microsoftonline.com/*' => Http::response([
        'access_token' => 'at-1', 'refresh_token' => 'rt-1', 'expires_in' => 3600,
        'id_token' => idToken(['preferred_username' => 'Sinistri@Assiponte.it', 'name' => 'Sinistri']),
    ])]);
    $this->tester->shouldReceive('imap')->once()->withArgs(fn ($c) => $c['oauth'] === true && $c['password'] === 'at-1' && $c['username'] === 'sinistri@assiponte.it');
    $this->tester->shouldReceive('smtp')->once();

    $redirect = $this->actingAs($this->admin)->get('/impostazioni/email/microsoft?email=sinistri@assiponte.it&nome=Assiponte')
        ->assertRedirect();
    $url = $redirect->headers->get('Location');
    expect($url)->toStartWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize')
        ->toContain('IMAP.AccessAsUser.All')->toContain('login_hint=sinistri');
    parse_str(parse_url($url, PHP_URL_QUERY), $q);

    $this->get('/impostazioni/email/microsoft/callback?code=abc&state='.$q['state'])
        ->assertRedirect('/impostazioni/email')
        ->assertSessionHas('success')
        ->assertSessionMissing('error');

    $s = TenantMailSettings::first();
    expect($s->auth_type)->toBe('microsoft')
        ->and($s->from_address)->toBe('sinistri@assiponte.it')
        ->and($s->from_name)->toBe('Assiponte')
        ->and($s->imap_host)->toBe('outlook.office365.com')
        ->and($s->oauth_refresh_token)->toBe('rt-1')
        ->and($s->password)->toBeNull();
    Bus::assertDispatched(SyncTenantMailboxJob::class);
});

test('Microsoft: state sbagliato o consenso negato non salvano nulla', function () {
    config(['services.microsoft_mail.client_id' => 'cid', 'services.microsoft_mail.client_secret' => 'sec']);

    $this->actingAs($this->admin)->get('/impostazioni/email/microsoft');
    $this->get('/impostazioni/email/microsoft/callback?code=abc&state=falso')->assertSessionHas('error');

    $this->get('/impostazioni/email/microsoft');
    $state = session('microsoft_mail.state');
    $this->get("/impostazioni/email/microsoft/callback?state={$state}&error=consent_required&error_description=AADSTS65001+admin+consent")
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'amministratore'));

    expect(TenantMailSettings::count())->toBe(0);
});

test('Microsoft: se manca solo l\'invio la casella si salva lo stesso con l\'avviso', function () {
    config(['services.microsoft_mail.client_id' => 'cid', 'services.microsoft_mail.client_secret' => 'sec']);
    Http::fake(['login.microsoftonline.com/*' => Http::response([
        'access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600, 'id_token' => idToken(['email' => 'a@assiponte.it']),
    ])]);
    $this->tester->shouldReceive('imap')->once();
    $this->tester->shouldReceive('smtp')->andThrow(new RuntimeException('535 5.7.139 Authentication unsuccessful, SmtpClientAuthentication is disabled for the Tenant.'));
    $this->tester->shouldReceive('spiega')->passthru();

    $this->actingAs($this->admin)->get('/impostazioni/email/microsoft');
    $this->get('/impostazioni/email/microsoft/callback?code=x&state='.session('microsoft_mail.state'))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'SMTP autenticato'));

    expect(TenantMailSettings::first()->is_active)->toBeTrue();
});

test('Microsoft: il token scaduto si rinnova da solo e si salva', function () {
    config(['services.microsoft_mail.client_id' => 'cid', 'services.microsoft_mail.client_secret' => 'sec']);
    Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'nuovo', 'refresh_token' => 'rt-2', 'expires_in' => 3600])]);

    $s = TenantMailSettings::create([
        'tenant_id' => $this->tenant->id, 'auth_type' => 'microsoft', 'username' => 'a@assiponte.it',
        'imap_host' => 'outlook.office365.com', 'oauth_refresh_token' => 'rt-1', 'oauth_access_token' => 'vecchio',
        'oauth_expires_at' => now()->subMinute(),
    ]);

    $config = $s->imapClientConfig();
    expect($config['password'])->toBe('nuovo')->and($config['authentication'])->toBe('oauth')
        ->and($s->fresh()->oauth_refresh_token)->toBe('rt-2');

    // Ancora valido: nessuna nuova chiamata.
    expect($s->fresh()->smtpConfig()['password'])->toBe('nuovo');
    Http::assertSentCount(1);
});

test('Outlook mostra il pulsante Microsoft solo se l\'app è configurata', function () {
    config(['services.microsoft_mail.client_id' => null]);
    $this->actingAs($this->admin)->get('/impostazioni/email/microsoft')->assertNotFound();
    $this->get('/impostazioni/email')->assertInertia(fn (Assert $page) => $page->where('microsoftDisponibile', false));
});
