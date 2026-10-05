<?php

use App\Jobs\InviaEmailAvvisoPratica;
use App\Models\{Cliente, EmailLog, Pratica, Tenant, TenantStatus, User};
use Illuminate\Support\Facades\Bus;
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;

beforeEach(function () {
    // Sostituisce solo la consegna SMTP (il mailer dinamico del tenant non è intercettabile
    // da Mail::fake): il resto — destinatari, registro, date — è il codice reale.
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public array $failFor = [];
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void
        {
            $ok = ! in_array($to, $GLOBALS['avvisi_fail'] ?? [], true);
            EmailLog::registra(array_merge($logContext, [
                'tenant_id' => $tenant->id, 'to_address' => $to, 'status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : 'smtp ko',
            ]));
            if (! $ok) { throw new \RuntimeException('smtp ko'); }
        }
    });
    $GLOBALS['avvisi_fail'] = [];
    $this->tenant  = Tenant::create(['name' => 'Studio Test']);
    $this->status  = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    $this->creator = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user', 'email' => 'creatore@example.com']);
    $this->admin1  = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin', 'email' => 'admin1@example.com']);
    $this->admin2  = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin', 'email' => 'admin2@example.com']);
    $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'Mario', 'email' => 'mario@example.com']);
});

function nuovaPratica(array $o = []): Pratica
{
    return Pratica::create(array_merge([
        'tenant_id' => test()->tenant->id, 'utente_creatore_id' => test()->creator->id,
        'cliente_id' => test()->cliente->id, 'current_status_id' => test()->status->id,
        'data_prossimo_avviso' => today()->toDateString(),
    ], $o));
}

test('il comando include anche gli avvisi con data già passata', function () {
    Bus::fake();
    $passata = nuovaPratica(['data_prossimo_avviso' => today()->subDays(7)->toDateString()]);
    $oggi    = nuovaPratica();
    $futura  = nuovaPratica(['data_prossimo_avviso' => today()->addDay()->toDateString()]);

    $this->artisan('app:process-daily-reminders')->assertSuccessful();

    Bus::assertDispatched(InviaEmailAvvisoPratica::class, fn ($j) => $j->praticaId === $passata->id);
    Bus::assertDispatched(InviaEmailAvvisoPratica::class, fn ($j) => $j->praticaId === $oggi->id);
    Bus::assertNotDispatched(InviaEmailAvvisoPratica::class, fn ($j) => $j->praticaId === $futura->id);
});

test('l\'avviso va al creatore e a tutti gli amministratori attivi, con data riprogrammata', function () {
    $p = nuovaPratica(['data_prossimo_avviso' => today()->subDays(3)->toDateString()]);
    User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin', 'email' => 'off@example.com', 'is_active' => false]);

    app()->call([new InviaEmailAvvisoPratica($p->id), 'handle']);

    expect(EmailLog::acrossAllTenants()->where('tipo', 'avviso')->where('status', 'sent')->pluck('to_address')->sort()->values()->all())
        ->toBe(['admin1@example.com', 'admin2@example.com', 'creatore@example.com']);
    expect($p->fresh()->data_prossimo_avviso->toDateString())->toBeGreaterThan(today()->toDateString());
});

test('con creatore disattivato l\'avviso va comunque agli amministratori', function () {
    $p = nuovaPratica();
    $this->creator->update(['is_active' => false]);

    app()->call([new InviaEmailAvvisoPratica($p->id), 'handle']);

    expect(EmailLog::acrossAllTenants()->where('status', 'sent')->count())->toBe(2);
});

test('senza destinatari validi viene registrato come saltato e la data passa a domani', function () {
    $p = nuovaPratica(['data_prossimo_avviso' => today()->subDay()->toDateString()]);
    User::where('tenant_id', $this->tenant->id)->update(['is_active' => false]);

    app()->call([new InviaEmailAvvisoPratica($p->id), 'handle']);

    expect(EmailLog::acrossAllTenants()->where('status', 'skipped')->where('tipo', 'avviso')->count())->toBe(1);
    expect($p->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->addDay()->toDateString());
});

test('un retry non reinvia a chi ha già ricevuto oggi', function () {
    $p = nuovaPratica();

    app()->call([new InviaEmailAvvisoPratica($p->id), 'handle']);
    $p->update(['data_prossimo_avviso' => today()->toDateString()]);
    app()->call([new InviaEmailAvvisoPratica($p->id), 'handle']);

    expect(EmailLog::acrossAllTenants()->where('status', 'sent')->count())->toBe(3);
});

test('un destinatario che fallisce non blocca gli altri e la data avanza', function () {
    $p = nuovaPratica(['data_prossimo_avviso' => today()->subDays(2)->toDateString()]);
    $GLOBALS['avvisi_fail'] = ['admin1@example.com'];

    app()->call([new InviaEmailAvvisoPratica($p->id), 'handle']);

    $sent = EmailLog::acrossAllTenants()->where('status', 'sent')->pluck('to_address')->sort()->values()->all();
    expect($sent)->toBe(['admin2@example.com', 'creatore@example.com']);
    expect(EmailLog::acrossAllTenants()->where('status', 'failed')->count())->toBe(1);
    expect($p->fresh()->data_prossimo_avviso->toDateString())->toBeGreaterThan(today()->toDateString());
});

test('se nessuno riceve l\'avviso il job ritenta; esauriti i tentativi la data passa a domani, mai nel passato', function () {
    $p = nuovaPratica(['data_prossimo_avviso' => today()->subDays(2)->toDateString()]);
    $GLOBALS['avvisi_fail'] = ['creatore@example.com', 'admin1@example.com', 'admin2@example.com'];

    $job = new InviaEmailAvvisoPratica($p->id);
    expect(fn () => app()->call([$job, 'handle']))->toThrow(\RuntimeException::class);

    $job->failed(new \RuntimeException('smtp ko'));

    expect($p->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->addDay()->toDateString());
    expect(EmailLog::acrossAllTenants()->where('tipo', 'avviso')->where('status', 'failed')->where('error', 'like', 'Job avviso fallito%')->count())->toBe(1);
});

test('il comando di riallineamento sposta le date passate senza inviare nulla né toccare le altre', function () {
    $passata = nuovaPratica(['data_prossimo_avviso' => today()->subDays(9)->toDateString()]);
    $futura  = nuovaPratica(['data_prossimo_avviso' => today()->addDays(3)->toDateString()]);

    $this->artisan('app:realign-overdue-notices --dry-run')->assertSuccessful();
    expect($passata->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->subDays(9)->toDateString());

    $this->artisan('app:realign-overdue-notices')->assertSuccessful();

    expect($passata->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->addDays($this->tenant->getDefaultNoticeDays())->toDateString());
    expect($futura->fresh()->data_prossimo_avviso->toDateString())->toBe(today()->addDays(3)->toDateString());
    expect(EmailLog::acrossAllTenants()->count())->toBe(0);
});

test('gli avvisi stanno sulla coda prioritaria e il sync caselle è univoco per tenant', function () {
    expect((new InviaEmailAvvisoPratica(1))->queue)->toBe('automations');

    $sync = new \App\Jobs\SyncTenantMailboxJob(7);
    expect($sync)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($sync->uniqueId())->toBe('7');
});
