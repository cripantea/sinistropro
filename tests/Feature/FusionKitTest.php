<?php

use App\FusionKit\KitToken;
use App\FusionKit\LinkError;
use App\FusionKitAdapter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['fusionkit.kit_secret' => 'segreto-di-prova', 'fusionkit.product' => 'sinistropro', 'fusionkit.app_url' => 'http://localhost']);
    $this->tenant = Tenant::create(['name' => 'Agenzia Kit']);
    $this->user = User::factory()->create(['email' => 'mario@example.com', 'tenant_id' => $this->tenant->id, 'role' => 'tenant-admin', 'is_active' => true]);
});

it('protegge le API di Fusion Control con il segreto del Kit', function () {
    $this->getJson('/api/fusion/tenants')->assertStatus(401);
    $this->getJson('/api/fusion/tenants', ['Authorization' => 'Bearer sbagliato'])->assertStatus(401);
    $this->getJson('/api/fusion/tenants', ['Authorization' => 'Bearer segreto-di-prova'])
        ->assertOk()
        ->assertJsonPath('tenants.0.name', 'Agenzia Kit')
        ->assertJsonPath('tenants.0.contactEmail', 'mario@example.com');
});

it('mostra lo stato senza dettagli a chi non ha il segreto', function () {
    $this->getJson('/api/fusion/health')->assertOk()->assertExactJson(['ok' => true]);
    $this->getJson('/api/fusion/health', ['Authorization' => 'Bearer segreto-di-prova'])->assertOk()->assertJsonPath('product', 'sinistropro');
});

it('collega Fusion ID per email verificata e poi per sub', function () {
    $adapter = new FusionKitAdapter();
    $claims = ['sub' => 'zitadel-1', 'email' => 'mario@example.com', 'email_verified' => true, 'amr' => ['pwd']];
    expect($adapter->linkUser($claims)->id)->toBe($this->user->id);
    expect($this->user->fresh()->fusion_id_sub)->toBe('zitadel-1');
    expect($adapter->linkUser(['email' => 'altra@example.com', 'email_verified' => false] + $claims)->id)->toBe($this->user->id);
});

it('non collega email non verificate né account sconosciuti', function () {
    $adapter = new FusionKitAdapter();
    expect(fn () => $adapter->linkUser(['sub' => 'z2', 'email' => 'mario@example.com', 'email_verified' => false, 'amr' => []]))->toThrow(LinkError::class);
    expect(fn () => $adapter->linkUser(['sub' => 'z3', 'email' => 'nessuno@example.com', 'email_verified' => true, 'amr' => []]))->toThrow(LinkError::class);
});

it('chiede la 2FA al superadmin', function () {
    User::factory()->create(['email' => 'boss@fusionsoft.it', 'role' => 'superadmin', 'is_active' => true, 'tenant_id' => null]);
    $adapter = new FusionKitAdapter();
    expect(fn () => $adapter->linkUser(['sub' => 'z4', 'email' => 'boss@fusionsoft.it', 'email_verified' => true, 'amr' => ['pwd']]))->toThrow(LinkError::class);
    expect($adapter->linkUser(['sub' => 'z4', 'email' => 'boss@fusionsoft.it', 'email_verified' => true, 'amr' => ['pwd', 'mfa']])->email)->toBe('boss@fusionsoft.it');
});

it('blocca chi appartiene a un\'agenzia sospesa', function () {
    $this->tenant->forceFill(['suspended_at' => now()])->save();
    $this->actingAs($this->user)->get('/dashboard')->assertRedirect('/login');
});

it('entra come il cliente con un link di 60 secondi', function () {
    $res = $this->postJson('/api/fusion/impersonate', ['tenantId' => (string) $this->tenant->id], ['Authorization' => 'Bearer segreto-di-prova'])->assertOk();
    $query = parse_url($res->json('url'), PHP_URL_QUERY);
    parse_str($query, $q);
    $this->get('/auth/fusion/impersonate?token='.urlencode($q['token']))->assertRedirect();
    $this->assertAuthenticatedAs($this->user);
});

it('rifiuta link "entra come" scaduti o falsi', function () {
    $scaduto = KitToken::sign(['purpose' => 'impersonate', 'userId' => (string) $this->user->id], -1);
    $this->get('/auth/fusion/impersonate?token='.urlencode($scaduto))->assertRedirect('/login');
    $falso = KitToken::sign(['purpose' => 'impersonate', 'userId' => (string) $this->user->id], 60, 'altro-segreto');
    $this->get('/auth/fusion/impersonate?token='.urlencode($falso))->assertRedirect('/login');
    $this->assertGuest();
});

it('chiude tutte le sessioni di un utente', function () {
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);
    $this->postJson('/api/fusion/users/logout', ['email' => 'mario@example.com'], ['Authorization' => 'Bearer segreto-di-prova'])
        ->assertOk()->assertJsonPath('revoked', 1);
    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0);
});
