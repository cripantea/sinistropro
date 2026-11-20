<?php

use App\Jobs\ExecuteAutomationJob;
use App\Models\{Automation, Cliente, EmailLog, Pratica, Tenant, TenantStatus, User, WhatsappConversation};
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;

beforeEach(function () {
    $this->tenant  = Tenant::create(['name' => 'Agenzia Test']);
    $stato         = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Aperto', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->user    = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario Rossi', 'telefono' => '333 111 2222', 'email' => 'mario@example.com']);
    $this->pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'utente_creatore_id' => $this->user->id, 'cliente_id' => $this->cliente->id, 'current_status_id' => $stato->id]);
});

test('la chat WhatsApp del sinistro usa il cellulare del cliente e mostra i messaggi già inviati', function () {
    // messaggio partito dall'automazione di benvenuto (conversazione per numero)
    $conv = WhatsappConversation::create(['tenant_id' => $this->tenant->id, 'phone_number' => '393331112222', 'pratica_id' => $this->pratica->id]);

    $res = $this->actingAs($this->user)->getJson("/pratiche/{$this->pratica->id}/whatsapp")->assertOk();

    expect($res->json('phoneNumber'))->toBe('393331112222')
        ->and($res->json('conversation.id'))->toBe($conv->id);
});

test('senza cellulare del cliente il pannello lo dice e porta alla scheda cliente', function () {
    $this->cliente->update(['telefono' => null]);

    $res = $this->actingAs($this->user)->getJson("/pratiche/{$this->pratica->id}/whatsapp")->assertOk();

    expect($res->json('phoneNumber'))->toBeNull()->and($res->json('clienteId'))->toBe($this->cliente->id);
});

test('le email delle automazioni si vedono dentro al sinistro, col testo', function () {
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void
        {
            EmailLog::registra(array_merge($logContext, [
                'tenant_id' => $tenant->id, 'to_address' => $to, 'status' => 'sent',
                'subject' => $mailable->envelope()->subject ?? null, 'body_html' => $mailable->render(),
            ]));
        }
    });
    $auto = Automation::create([
        'tenant_id' => $this->tenant->id, 'name' => 'Benvenuto al cliente', 'trigger_type' => 'pratica_created',
        'channel' => 'email', 'recipients_to' => [['type' => 'cliente']], 'message_template' => 'Gentile {nome_cliente}, ci occupiamo noi del sinistro.', 'is_active' => true,
    ]);
    app()->call([new ExecuteAutomationJob($this->pratica, $auto), 'handle']);
    // email di un altro sinistro: non deve comparire
    EmailLog::registra(['tenant_id' => $this->tenant->id, 'pratica_id' => null, 'to_address' => 'x@example.com', 'status' => 'sent', 'subject' => 'Altro']);

    $msgs = $this->actingAs($this->user)->getJson("/pratiche/{$this->pratica->id}/email")->assertOk()->json('messages');

    expect($msgs)->toHaveCount(1)
        ->and($msgs[0]['automatica'])->toBe('Benvenuto al cliente')
        ->and($msgs[0]['toAddresses'])->toBe(['mario@example.com'])
        ->and($msgs[0]['bodyHtml'])->toContain('ci occupiamo noi del sinistro');
});

test('il testo delle email automatiche viene salvato nel registro', function () {
    $log = new class extends Mailable {
        public function build() { return $this->subject('Prova')->html('<p>Testo salvato</p>'); }
    };
    $r = new TenantMailerResolver();
    $m = new ReflectionMethod($r, 'registra');
    $m->invoke($r, $this->tenant, 'a@example.com', [], $log, ['pratica_id' => $this->pratica->id], 'sent');

    expect(EmailLog::acrossAllTenants()->first()->body_html)->toContain('Testo salvato');
});
