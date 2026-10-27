<?php

use App\Jobs\InviaEmailAvvisoPratica;
use App\Models\{Cliente, Contatto, EmailLog, Pratica, Tenant, TenantStatus, User};
use App\Services\Billing\StripeBilling;
use Illuminate\Support\Facades\URL;

// Abbonamento Stripe: sola lettura senza abbonamento, tenant "fatturati a parte" mai toccati,
// webhook firmato, prezzi personalizzati dal superadmin, link di pagamento firmato.

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Agenzia Stripe']);
    $this->tenant->forceFill(['billing_mode' => 'stripe'])->save();
    $this->stato  = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->admin  = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
    $this->superadmin = User::factory()->create(['tenant_id' => null, 'role' => 'superadmin']);

    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

function webhook(array $event)
{
    $payload = json_encode($event);
    $t = time();
    $sig = hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test');

    return test()->call('POST', '/api/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}",
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

function subscriptionEvent(string $type, string $id, string $status, array $extra = []): array
{
    return [
        'id' => 'evt_1', 'object' => 'event', 'type' => $type,
        'data' => ['object' => array_merge([
            'id' => $id, 'object' => 'subscription', 'customer' => 'cus_1', 'status' => $status,
            'cancel_at_period_end' => false, 'cancel_at' => null,
            'metadata' => ['app' => 'sinistropro', 'tenant_id' => (string) test()->tenant->id],
            'items' => ['object' => 'list', 'data' => [[
                'id' => 'si_1', 'object' => 'subscription_item', 'current_period_end' => now()->addMonth()->timestamp,
                'price' => ['id' => 'price_1', 'object' => 'price', 'recurring' => ['interval' => 'month']],
            ]]],
        ], $extra)],
    ];
}

test('i tenant già esistenti sono "fatturati a parte" e hanno sempre accesso pieno', function () {
    $vecchio = Tenant::create(['name' => 'Malacrida']);

    expect($vecchio->fresh()->billing_mode)->toBe('esterno')
        ->and($vecchio->fresh()->hasAccess())->toBeTrue();
});

test('il superadmin crea agenzie nuove che pagano con Stripe', function () {
    $this->actingAs($this->superadmin)->post('/superadmin/tenants', [
        'name' => 'Agenzia Nuova', 'admin_name' => 'Mario Rossi', 'admin_email' => 'mario@nuova.it', 'admin_password' => 'Provvisoria23',
        'price_monthly' => '79',
    ])->assertRedirect()->assertSessionHas('credenziali', fn ($testo) => str_contains($testo, 'mario@nuova.it') && str_contains($testo, 'Provvisoria23') && str_contains($testo, '/paga/'));

    $nuova = Tenant::where('name', 'Agenzia Nuova')->first();
    expect($nuova->billing_mode)->toBe('stripe')->and($nuova->hasAccess())->toBeFalse()
        ->and($nuova->initialStatus())->not->toBeNull()
        ->and($nuova->priceCents('month'))->toBe(7900)
        ->and($nuova->priceCents('year'))->toBe(79000);

    $admin = User::where('email', 'mario@nuova.it')->first();
    expect($admin->tenant_id)->toBe($nuova->id)->and($admin->role)->toBe('tenant-admin')->and($admin->must_change_password)->toBeTrue();

    // Primo accesso: ogni pagina porta a "Scegli la tua password"; dopo si va a pagare.
    $this->post('/logout');
    $this->post('/login', ['email' => 'mario@nuova.it', 'password' => 'Provvisoria23']);
    $this->get('/dashboard')->assertRedirect(route('password.scegli'));
    $this->get('/scegli-password')->assertOk();
    $this->put('/scegli-password', ['password' => 'corta'])->assertSessionHasErrors('password');
    $this->put('/scegli-password', ['password' => 'LaMiaPassword9'])->assertRedirect(route('abbonamento'));

    expect($admin->fresh()->must_change_password)->toBeFalse()
        ->and(\Illuminate\Support\Facades\Hash::check('LaMiaPassword9', $admin->fresh()->password))->toBeTrue();
    $this->get('/dashboard')->assertOk();
});

test('senza abbonamento si consulta ma non si modifica', function () {
    $this->actingAs($this->user)->get('/pratiche')->assertOk();
    $this->actingAs($this->user)->get('/abbonamento')->assertOk();

    $this->actingAs($this->user)->post('/contatti', ['nome' => 'Perito', 'tags' => ['perito']])
        ->assertRedirect()
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'sola lettura'));

    expect(Contatto::acrossAllTenants()->count())->toBe(0);
});

test('il superadmin non è mai bloccato dalla sola lettura', function () {
    $this->actingAs($this->superadmin)
        ->post("/superadmin/tenants/{$this->tenant->id}/context")
        ->assertSessionMissing('error');
});

test('l\'accesso gratuito fino a una data e l\'abbonamento attivo sbloccano le modifiche', function () {
    $this->tenant->forceFill(['free_until' => today()])->save();
    expect($this->tenant->fresh()->hasAccess())->toBeTrue()->and($this->tenant->fresh()->billingState())->toBe('gratis');

    $this->tenant->forceFill(['free_until' => today()->subDay(), 'subscription_status' => 'past_due'])->save();
    expect($this->tenant->fresh()->hasAccess())->toBeTrue()->and($this->tenant->fresh()->billingState())->toBe('in_ritardo');

    $this->tenant->forceFill(['subscription_status' => 'canceled'])->save();
    expect($this->tenant->fresh()->hasAccess())->toBeFalse();
});

test('senza abbonamento gli avvisi automatici non partono', function () {
    $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario', 'email' => 'mario@example.com']);
    $p = Pratica::create([
        'tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->admin->id, 'cliente_id' => $cliente->id,
        'current_status_id' => $this->stato->id, 'data_prossimo_avviso' => today()->toDateString(),
    ]);

    app()->call([new InviaEmailAvvisoPratica($p->id), 'handle']);

    expect(EmailLog::acrossAllTenants()->count())->toBe(0);
});

test('prezzo personalizzato del tenant, altrimenti quello standard', function () {
    expect($this->tenant->priceCents('month'))->toBe(9900)->and($this->tenant->priceCents('year'))->toBe(99000);

    $this->tenant->forceFill(['price_monthly_cents' => 7900])->save();
    expect($this->tenant->fresh()->priceCents('month'))->toBe(7900)->and($this->tenant->fresh()->priceCents('year'))->toBe(79000);

    $this->tenant->forceFill(['price_yearly_cents' => 75000])->save();
    expect($this->tenant->fresh()->priceCents('year'))->toBe(75000);
});

test('il webhook aggiorna lo stato dell\'abbonamento e sblocca il tenant', function () {
    webhook(subscriptionEvent('customer.subscription.created', 'sub_1', 'active'))->assertOk();

    $t = $this->tenant->fresh();
    expect($t->stripe_subscription_id)->toBe('sub_1')
        ->and($t->subscription_status)->toBe('active')
        ->and($t->subscription_interval)->toBe('month')
        ->and($t->hasAccess())->toBeTrue();

    webhook(subscriptionEvent('customer.subscription.deleted', 'sub_1', 'canceled'))->assertOk();
    expect($this->tenant->fresh()->hasAccess())->toBeFalse();
});

test('il webhook ignora firme non valide e abbonamenti di altri prodotti Fusion', function () {
    $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=x'], '{}')->assertStatus(400);

    $altro = subscriptionEvent('customer.subscription.created', 'sub_moduli', 'active');
    $altro['data']['object']['metadata'] = ['app' => 'moduli', 'tenant_id' => (string) $this->tenant->id];
    webhook($altro)->assertOk();

    expect($this->tenant->fresh()->stripe_subscription_id)->toBeNull();
});

test('gli eventi di un vecchio abbonamento non toccano quello nuovo', function () {
    $this->tenant->forceFill(['stripe_subscription_id' => 'sub_nuovo', 'subscription_status' => 'active'])->save();

    webhook(subscriptionEvent('customer.subscription.deleted', 'sub_vecchio', 'canceled'))->assertOk();

    expect($this->tenant->fresh()->subscription_status)->toBe('active');
});

test('il superadmin imposta prezzi in euro e chi paga già passa al nuovo prezzo', function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    $this->tenant->forceFill(['stripe_subscription_id' => 'sub_1', 'subscription_status' => 'active'])->save();
    $this->mock(StripeBilling::class)->shouldReceive('applyCurrentPrice')->once();

    $this->actingAs($this->superadmin)->post("/superadmin/tenants/{$this->tenant->id}/billing", [
        'billing_mode' => 'stripe', 'price_monthly' => '79', 'price_yearly' => '790.50', 'setup_fee' => '', 'free_until' => '',
    ])->assertSessionHas('success');

    $t = $this->tenant->fresh();
    expect($t->price_monthly_cents)->toBe(7900)->and($t->price_yearly_cents)->toBe(79050)->and($t->setup_fee_cents)->toBeNull();
});

test('solo il tenant-admin può pagare', function () {
    $this->actingAs($this->user)->post('/abbonamento/checkout', ['interval' => 'month'])->assertForbidden();
});

test('il link di pagamento funziona solo se firmato', function () {
    $this->get("/paga/{$this->tenant->id}")->assertForbidden();
    $this->get(URL::signedRoute('paga', $this->tenant))->assertOk();
    $this->get(URL::signedRoute('paga.grazie', $this->tenant) . '&session_id=cs_test')->assertOk();
});

test('il superadmin vede stato e link di pagamento nella scheda e nell\'elenco agenzie', function () {
    $this->actingAs($this->superadmin)->get("/superadmin/tenants/{$this->tenant->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('billing.state', 'non_attivo')
            ->where('billing.billing_mode', 'stripe')
            ->where('billing.price_monthly_cents', 9900)
            ->where('billing.payment_link', fn ($url) => str_contains($url, '/paga/' . $this->tenant->id) && str_contains($url, 'signature=')));

    $this->actingAs($this->superadmin)->get('/superadmin/tenants')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tenants.data', fn ($rows) => collect($rows)->pluck('billing_state')->contains('non_attivo')));
});

/** StripeClient finto: registra le chiamate e risponde come Stripe (solo i servizi usati qui). */
function stripeFinto(array &$chiamate): \Stripe\StripeClient
{
    $sub = fn (array $extra = []) => \Stripe\Util\Util::convertToStripeObject(array_merge([
        'id' => 'sub_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active', 'cancel_at_period_end' => false,
        'items' => ['object' => 'list', 'data' => [['current_period_end' => now()->addMonth()->timestamp, 'price' => ['recurring' => ['interval' => 'month']]]]],
    ], $extra), []);

    $servizio = fn (array $metodi) => new class($metodi) {
        public function __construct(private array $metodi) {}
        public function __call($nome, $args) { return ($this->metodi[$nome])(...$args); }
    };

    $servizi = [
        'subscriptions' => $servizio([
            'update' => function ($id, $p) use (&$chiamate, $sub) { $chiamate[] = ['update', $id, $p]; return $sub(['cancel_at_period_end' => $p['cancel_at_period_end']]); },
            'cancel' => function ($id) use (&$chiamate, $sub) { $chiamate[] = ['cancel', $id]; return $sub(['status' => 'canceled']); },
        ]),
        'charges' => $servizio(['all' => fn () => \Stripe\Util\Util::convertToStripeObject(['object' => 'list', 'data' => [
            ['id' => 'ch_1', 'object' => 'charge', 'status' => 'succeeded', 'refunded' => false, 'amount_refunded' => 0, 'amount' => 7900],
        ]], [])]),
        'refunds' => $servizio(['create' => function ($p) use (&$chiamate) { $chiamate[] = ['refund', $p['charge']]; return (object) []; }]),
        'invoices' => $servizio(['all' => fn () => \Stripe\Util\Util::convertToStripeObject(['object' => 'list', 'data' => [
            ['id' => 'in_1', 'object' => 'invoice', 'number' => 'ABC-0001', 'created' => now()->timestamp, 'total' => 7900, 'status' => 'paid', 'hosted_invoice_url' => 'https://pay.stripe.com/x', 'invoice_pdf' => 'https://pay.stripe.com/x.pdf'],
        ]], [])]),
    ];

    return new class($servizi) extends \Stripe\StripeClient {
        public function __construct(private array $servizi) { parent::__construct(['api_key' => 'sk_test_x']); }
        public function __get($nome) { return $this->servizi[$nome]; }
    };
}

test('il superadmin vede le fatture, disdice, annulla e rimborsa', function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    $chiamate = [];
    app()->instance(\Stripe\StripeClient::class, stripeFinto($chiamate));
    $this->tenant->forceFill(['stripe_customer_id' => 'cus_1', 'stripe_subscription_id' => 'sub_1', 'subscription_status' => 'active'])->save();
    $this->actingAs($this->superadmin);
    $url = "/superadmin/tenants/{$this->tenant->id}/billing";

    $this->getJson("{$url}/fatture")->assertOk()->assertJsonPath('fatture.0.numero', 'ABC-0001')->assertJsonPath('fatture.0.importo_cents', 7900);

    $this->post("{$url}/azione", ['azione' => 'disdici'])->assertSessionHas('success');
    expect($this->tenant->fresh()->subscription_cancel_at_period_end)->toBeTrue()->and($this->tenant->fresh()->hasAccess())->toBeTrue();

    $this->post("{$url}/azione", ['azione' => 'riprendi'])->assertSessionHas('success');
    expect($this->tenant->fresh()->subscription_cancel_at_period_end)->toBeFalse();

    $this->post("{$url}/azione", ['azione' => 'rimborsa'])->assertSessionHas('success', fn ($m) => str_contains($m, '79,00'));
    expect($this->tenant->fresh()->subscription_status)->toBe('canceled')->and($this->tenant->fresh()->hasAccess())->toBeFalse()
        ->and(collect($chiamate)->pluck(0)->all())->toBe(['update', 'update', 'refund', 'cancel']);

    // Abbonamento già annullato: niente disdetta. Solo il superadmin può farlo.
    $this->post("{$url}/azione", ['azione' => 'disdici'])->assertStatus(422);
    $this->actingAs($this->admin)->post("{$url}/azione", ['azione' => 'annulla'])->assertRedirect();
    expect($chiamate)->toHaveCount(4);
});
