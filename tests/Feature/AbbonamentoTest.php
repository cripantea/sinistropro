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
    $this->post('/login', ['email' => 'mario@nuova.it', 'telefono' => '333 1234567', 'attivita' => 'agenzia_assicurativa', 'sinistri' => '30–100', 'password' => 'Provvisoria23']);
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
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'attiva prima la prova'));

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

// ── Prova gratuita (solo registrazione da soli) ───────────────────────────

test('30 giorni di prova con carta sia per chi si registra da solo sia per chi arriva dalla demo', function () {
    $this->post('/registrati', ['nome' => 'Mario Rossi', 'agenzia' => 'Agenzia Prova', 'email' => 'prova@agenzia.it', 'telefono' => '333 1234567', 'attivita' => 'agenzia_assicurativa', 'sinistri' => '30–100', 'password' => 'password123']);
    $registrato = User::where('email', 'prova@agenzia.it')->first();

    expect($registrato->tenant->trialDays())->toBe(30)
        ->and($this->tenant->trialDays())->toBe(30); // creato dal superadmin dopo una demo: stessa offerta

    config(['services.stripe.secret' => 'sk_test_x']);
    $this->mock(StripeBilling::class)->shouldReceive('checkoutUrl')->once()
        ->withArgs(fn ($tenant, $interval, $ok, $annulla, $email, $giorni) => $tenant->id === $registrato->tenant_id && $giorni === 30)
        ->andReturn('https://checkout.stripe.com/x');

    $this->actingAs($registrato)->post('/abbonamento/checkout', ['interval' => 'month'])->assertRedirect('https://checkout.stripe.com/x');
});

test('niente seconda prova dopo un abbonamento, né con un costo di attivazione', function () {
    $this->tenant->forceFill(['settings' => ['registrazione_da_solo' => true]])->save();
    expect($this->tenant->fresh()->trialDays())->toBe(30);

    $this->tenant->forceFill(['setup_fee_cents' => 50000])->save();
    expect($this->tenant->fresh()->trialDays())->toBe(0);

    $this->tenant->forceFill(['setup_fee_cents' => 0, 'stripe_subscription_id' => 'sub_vecchio', 'subscription_status' => 'canceled'])->save();
    expect($this->tenant->fresh()->trialDays())->toBe(0);
});

test('in prova ha accesso pieno, è "prova" e non conta nell\'incasso', function () {
    webhook(subscriptionEvent('customer.subscription.created', 'sub_prova', 'trialing'));

    $t = $this->tenant->fresh();
    expect($t->hasAccess())->toBeTrue()
        ->and($t->billingState())->toBe('prova');

    // Disdetta durante la prova: resta "prova" (niente addebito), non "disdetto".
    webhook(subscriptionEvent('customer.subscription.updated', 'sub_prova', 'trialing', ['cancel_at_period_end' => true]));
    expect($this->tenant->fresh()->billingState())->toBe('prova');

    $riga = collect(\App\Support\CruscottoSuperadmin::dati()['agenzie'])->firstWhere('id', $this->tenant->id);
    expect($riga['stato'])->toBe('prova')->and($riga['mensile_cents'])->toBe(0);
});

test('dopo la registrazione la carta si inserisce nella stessa pagina, con la prova', function () {
    config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.key' => 'pk_test_x']);
    $this->post('/registrati', ['nome' => 'Mario Rossi', 'agenzia' => 'Agenzia Carta', 'email' => 'carta@agenzia.it', 'telefono' => '333 1234567', 'attivita' => 'agenzia_assicurativa', 'sinistri' => '30–100', 'password' => 'password123'])
        ->assertRedirect(route('registrati.carta'));

    $this->get('/registrati/carta')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Registrati')->where('passo', 'carta')->where('giorniProva', 30)->where('stripeKey', 'pk_test_x'));

    $this->mock(StripeBilling::class)->shouldReceive('embeddedClientSecret')->once()
        ->withArgs(fn ($tenant, $interval, $ritorno, $email, $giorni) => $interval === 'month' && $giorni === 30 && $email === 'carta@agenzia.it')
        ->andReturn('cs_secret_x');

    $this->postJson('/abbonamento/sessione', ['interval' => 'month'])->assertOk()->assertJson(['clientSecret' => 'cs_secret_x']);
});

test('la pagina carta non serve a chi ha già un abbonamento o non ha diritto alla prova', function () {
    $this->actingAs($this->admin)->get('/registrati/carta')->assertOk(); // dopo la demo: stessa prova
    $this->tenant->forceFill(['stripe_subscription_id' => 'sub_vecchio', 'subscription_status' => 'canceled'])->save();
    $this->actingAs($this->admin->fresh())->get('/registrati/carta')->assertRedirect(route('abbonamento'));
    $this->actingAs($this->user)->postJson('/abbonamento/sessione', ['interval' => 'month'])->assertForbidden();
});

test('dopo la carta si prenota la call col tecnico con i dati già compilati, poi si apre il primo sinistro', function () {
    config(['billing.onboarding_url' => 'https://crm.example.it/prenota/onb']);
    $this->post('/registrati', ['nome' => 'Mario Rossi', 'agenzia' => 'Agenzia Tecnico', 'email' => 'tecnico@agenzia.it', 'telefono' => '333 1234567', 'attivita' => 'agenzia_assicurativa', 'sinistri' => '30–100', 'password' => 'password123']);
    $tenant = User::where('email', 'tecnico@agenzia.it')->first()->tenant;
    expect($tenant->getSetting('telefono_registrazione'))->toBe('333 1234567');

    $tenant->forceFill(['stripe_subscription_id' => 'sub_p', 'subscription_status' => 'trialing', 'subscription_ends_at' => now()->addDays(30)])->save();
    $this->get('/abbonamento/attivo')->assertRedirect(route('registrati.onboarding'));

    $this->get('/registrati/tecnico')->assertOk()->assertInertia(fn ($page) => $page->where('passo', 'onboarding')
        ->where('bookingUrl', fn ($url) => str_starts_with($url, 'https://crm.example.it/prenota/onb?embed=1') && str_contains($url, 'email=tecnico%40agenzia.it') && str_contains($url, 'phone=333+1234567')));

    // Senza calendario configurato si va dritti al primo sinistro, con la guida.
    config(['billing.onboarding_url' => null]);
    $this->get('/abbonamento/attivo')->assertRedirect(route('pratiche.create'))->assertSessionHas('primi_passi', 'primo_sinistro');
    $this->get('/registrati/tecnico')->assertRedirect(route('pratiche.create'));
    $this->get('/registrati/inizia')->assertRedirect(route('pratiche.create'))->assertSessionHas('primi_passi', 'primo_sinistro');
});

test('promemoria fine prova: una email 7 giorni prima, una volta sola, mai se ha disdetto', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $this->tenant->forceFill(['stripe_subscription_id' => 'sub_p', 'subscription_status' => 'trialing', 'subscription_ends_at' => now()->addDays(6)])->save();

    $this->artisan('app:promemoria-fine-prova')->assertSuccessful();
    $this->artisan('app:promemoria-fine-prova')->assertSuccessful();
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\FineProvaMail::class, 1);
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\FineProvaMail::class, fn ($m) => $m->hasTo($this->admin->email));

    $altro = Tenant::create(['name' => 'Disdetta']);
    $altro->forceFill(['billing_mode' => 'stripe', 'stripe_subscription_id' => 'sub_d', 'subscription_status' => 'trialing', 'subscription_ends_at' => now()->addDays(3), 'subscription_cancel_at_period_end' => true])->save();
    User::factory()->create(['tenant_id' => $altro->id, 'role' => 'tenant-admin']);
    $this->artisan('app:promemoria-fine-prova')->assertSuccessful();
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\FineProvaMail::class, 1);

    // L'email si genera senza errori.
    expect((new \App\Mail\FineProvaMail($this->tenant->fresh()))->render())->toContain('99 €');
});

test('registrazione e prova attiva vanno al CRM con attività e sinistri gestiti', function () {
    config(['services.fusion_crm.webhook_url' => 'https://crm.test/api/hooks/x', 'services.fusion_crm.webhook_token' => 'tok']);
    \Illuminate\Support\Facades\Http::fake();

    $this->post('/registrati', ['nome' => 'Mario Rossi', 'agenzia' => 'Studio Rossi', 'email' => 'crm@agenzia.it', 'telefono' => '333 1234567', 'attivita' => 'studio_infortunistica', 'sinistri' => 'più di 300', 'password' => 'password123']);
    $this->app->terminate();

    \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->hasHeader('X-Fusion-Token', 'tok')
        && $r['tags'] === ['sinistropro', 'prova-registrato']
        && $r['customFields']['attivita'] === 'Studio di infortunistica'
        && $r['customFields']['pratiche_mese'] === 'più di 300'
        && $r['phone'] === '333 1234567');

    auth()->logout();
    $this->post('/registrati', ['nome' => 'X', 'agenzia' => 'Y', 'email' => 'no@agenzia.it', 'telefono' => '333 1234567', 'attivita' => 'idraulico', 'sinistri' => '30–100', 'password' => 'password123'])
        ->assertSessionHasErrors('attivita');
});

test('Meta: la registrazione va alla Conversions API solo con il consenso, e salva da quale annuncio arriva', function () {
    config(['services.meta.pixel_id' => '123', 'services.meta.capi_token' => 'tok', 'services.fusion_crm.webhook_url' => null]);
    \Illuminate\Support\Facades\Http::fake();

    $this->get('/registrati?fbclid=ABC&utm_content=P1')->assertOk();
    $this->withUnencryptedCookie('sp-consenso', 'si')
        ->post('/registrati', ['nome' => 'Mario Rossi', 'agenzia' => 'Meta Test', 'email' => 'meta@agenzia.it', 'telefono' => '333 1234567', 'attivita' => 'broker', 'sinistri' => '30–100', 'password' => 'password123'])
        ->assertSessionHas('traccia', fn ($t) => $t['event'] === 'CompleteRegistration');
    $this->app->terminate();

    $tenant = User::where('email', 'meta@agenzia.it')->first()->tenant;
    expect($tenant->getSetting('attribuzione.fbclid'))->toBe('ABC')->and($tenant->getSetting('attribuzione.utm_content'))->toBe('P1');
    \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com/v26.0/123/events')
        && $r['data'][0]['event_name'] === 'CompleteRegistration'
        && $r['data'][0]['event_id'] === "sp-registrazione-{$tenant->id}"
        && str_starts_with($r['data'][0]['user_data']['fbc'], 'fb.1.'));

    // Senza consenso (rifiutato): niente Conversions API.
    auth()->logout();
    $this->withUnencryptedCookie('sp-consenso', 'no')->post('/registrati', ['nome' => 'Anna Bianchi', 'agenzia' => 'No Consenso', 'email' => 'no@agenzia.it', 'telefono' => '333 1234567', 'attivita' => 'broker', 'sinistri' => '30–100', 'password' => 'password123']);
    $this->app->terminate();
    $senza = User::where('email', 'no@agenzia.it')->first()->tenant_id;
    $ids = \Illuminate\Support\Facades\Http::recorded(fn ($r) => str_contains($r->url(), 'graph.facebook.com'))->map(fn ($p) => $p[0]['data'][0]['event_id'])->unique()->values()->all();
    expect($ids)->toBe(["sp-registrazione-{$tenant->id}"])->and($ids)->not->toContain("sp-registrazione-{$senza}");
});
