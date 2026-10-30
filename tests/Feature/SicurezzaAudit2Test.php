<?php

use App\Models\{Automation, Cliente, Contatto, EmailLog, Pratica, Tenant, TenantStatus, User};
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\{Auth, Hash, Http, Password};
use Stripe\StripeClient;

// Regressioni del secondo audit di sicurezza (29/10/2026): sessioni dopo il cambio
// password, scegli-password, automazioni agli utenti disattivati, checkout Stripe
// vecchio, telefoni della rubrica agli esterni, token del widget WhatsApp.

function sa2Obj(array $a) { return json_decode(json_encode($a)); }

class Sa2FakeStripe extends StripeClient
{
    public $checkout;
    public $subscriptions;

    public function __construct(array $sessions, array $subs)
    {
        parent::__construct(['api_key' => 'sk_test_dummy']);
        $this->checkout = (object) ['sessions' => new class($sessions) { public function __construct(public array $s) {} public function retrieve($id) { return $this->s[$id]; } }];
        $this->subscriptions = new class($subs) { public function __construct(public array $s) {} public function retrieve($id) { return $this->s[$id]; } };
    }
}

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Studio A']);
    $this->stato0 = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->admin  = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Cliente A', 'email' => 'cliente@example.test']);
});

test('dopo il reset della password le sessioni già aperte non valgono più', function () {
    // Sessione aperta da chi ha registrato l'agenzia con l'email di un altro (o da un cookie rubato).
    $utente = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin', 'email' => 'vittima@example.test', 'password' => Hash::make('attaccante-1')]);
    $vecchiaSessione = ['password_hash_web' => Auth::guard('web')->hashPasswordForCookie($utente->password)];

    $this->actingAs($utente)->withSession($vecchiaSessione)->get('/dashboard')->assertOk();

    // Il vero proprietario reimposta la password.
    $token = Password::broker()->createToken($utente);
    Auth::forgetGuards();
    $this->post('/reset-password', ['token' => $token, 'email' => 'vittima@example.test', 'password' => 'Vittima-Nuova-99', 'password_confirmation' => 'Vittima-Nuova-99'])
        ->assertRedirect(route('login'));

    $this->actingAs($utente->fresh())->withSession($vecchiaSessione)->get('/dashboard')->assertRedirect(route('login'));
});

test('scegli-password senza password attuale funziona solo con la password provvisoria', function () {
    $this->admin->forceFill(['password' => Hash::make('Vecchia-Pass-1')])->save();

    $this->actingAs($this->admin)->put('/scegli-password', ['password' => 'Nuova-Pass-77'])->assertForbidden();
    expect(Hash::check('Vecchia-Pass-1', $this->admin->fresh()->password))->toBeTrue();

    $this->admin->forceFill(['must_change_password' => true])->save();
    $this->actingAs($this->admin->fresh())->put('/scegli-password', ['password' => 'Nuova-Pass-77'])->assertRedirect();
    expect(Hash::check('Nuova-Pass-77', $this->admin->fresh()->password))->toBeTrue();
});

test('le automazioni non mandano email agli utenti disattivati', function () {
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void
        {
            EmailLog::registra(array_merge($logContext, ['tenant_id' => $tenant->id, 'to_address' => $to, 'cc_addresses' => $cc ?: null, 'status' => 'sent']));
        }
    });

    $stato1 = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Chiusa', 'color' => '#111', 'order' => 1]);
    $ex = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user', 'email' => 'ex@example.test']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $ex->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->stato0->id]);
    Automation::create([
        'tenant_id' => $this->tenant->id, 'name' => 'A', 'trigger_type' => 'status', 'tenant_status_id' => $stato1->id,
        'channel' => 'email', 'recipients_to' => [['type' => 'cliente'], ['type' => 'gestore'], ['type' => 'user', 'user_id' => $ex->id]],
        'recipients_cc' => [['type' => 'user', 'user_id' => $ex->id]],
        'message_template' => 'Pratica {numero_pratica}', 'is_active' => true,
    ]);

    $this->actingAs($this->admin)->patch("/team/{$ex->id}/toggle-active")->assertRedirect();
    $this->actingAs($this->admin)->patchJson("/pratiche/{$pratica->id}/status", ['current_status_id' => $stato1->id])->assertOk();

    $log = EmailLog::acrossAllTenants()->where('status', 'sent')->get();
    expect($log->pluck('to_address')->all())->toBe(['cliente@example.test'])
        ->and($log->pluck('cc_addresses')->flatten()->filter()->all())->not->toContain('ex@example.test');
});

test('un checkout Stripe vecchio non sostituisce l\'abbonamento attivo', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.secret' => 'sk_test_dummy']);
    $this->tenant->forceFill(['billing_mode' => 'stripe', 'stripe_customer_id' => 'cus_1', 'stripe_subscription_id' => 'sub_new', 'subscription_status' => 'active'])->save();
    $sub = fn (string $id, string $status) => sa2Obj([
        'id' => $id, 'customer' => 'cus_1', 'status' => $status, 'cancel_at_period_end' => false, 'cancel_at' => null,
        'items' => ['data' => [['current_period_end' => now()->addMonth()->timestamp, 'price' => ['recurring' => ['interval' => 'month']]]]],
    ]);
    $vecchia = ['id' => 'cs_old', 'mode' => 'subscription', 'status' => 'complete', 'client_reference_id' => (string) $this->tenant->id,
        'customer' => 'cus_1', 'subscription' => 'sub_old', 'metadata' => ['app' => config('billing.stripe_app'), 'tenant_id' => (string) $this->tenant->id]];
    app()->instance(StripeClient::class, new Sa2FakeStripe(['cs_old' => sa2Obj($vecchia)], ['sub_old' => $sub('sub_old', 'canceled'), 'sub_new' => $sub('sub_new', 'active')]));
    $esterno = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'external']);

    // Ritorno dal checkout riaperto dalla cronologia.
    $this->actingAs($esterno)->get('/abbonamento/attivo?session_id=cs_old')->assertRedirect();

    // Webhook vecchio riconsegnato.
    $payload = json_encode(['id' => 'evt_old', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => $vecchia]]);
    $t = time();
    $firma = hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test');
    $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$firma}", 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

    $tenant = $this->tenant->fresh();
    expect($tenant->stripe_subscription_id)->toBe('sub_new')
        ->and($tenant->subscription_status)->toBe('active')
        ->and($tenant->hasAccess())->toBeTrue();
});

test('la pagina del sinistro non manda i telefoni della rubrica', function () {
    Contatto::create(['tenant_id' => $this->tenant->id, 'tags' => ['perito'], 'nome' => 'Perito A', 'telefono' => '0999PERITO']);
    $pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $this->stato0->id]);
    $esterno = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'external']);

    $props = $this->actingAs($esterno)->get("/pratiche/{$pratica->id}")->assertOk()->viewData('page')['props'];

    expect($props['periti'][0]['nome'])->toBe('Perito A')
        ->and($props['periti'][0])->not->toHaveKey('telefono');
});

test('il token del widget WhatsApp lo riceve solo il tenant-admin', function () {
    Http::preventStrayRequests();
    config(['services.fusionwa.base_url' => 'https://fusionwa.test', 'services.fusionwa.api_key' => 'k', 'services.fusionwa.api_secret' => 's']);
    Http::fake(['fusionwa.test/api/v1/widget/token' => Http::response(['token' => 'TOK'], 200)]);

    foreach (['user', 'external'] as $ruolo) {
        $u = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => $ruolo]);
        expect($this->actingAs($u)->get(route('whatsapp.index'))->assertOk()->viewData('page')['props']['fusionwaToken'])->toBeNull();
    }

    expect($this->actingAs($this->admin)->get(route('whatsapp.index'))->viewData('page')['props']['fusionwaToken'])->toBe('TOK');
});
