<?php

use App\Models\{Cliente, EmailLog, Pratica, Tenant, TenantStatus, User};
use App\Services\TenantMailerResolver;
use Illuminate\Mail\Mailable;

beforeEach(function () {
    // Solo la consegna è finta: ogni invio finisce nel registro email.
    app()->bind(TenantMailerResolver::class, fn () => new class extends TenantMailerResolver {
        public function send(Tenant $tenant, string $to, Mailable $mailable, array $cc = [], array $logContext = []): void
        {
            EmailLog::registra(array_merge($logContext, ['tenant_id' => $tenant->id, 'to_address' => $to, 'status' => 'sent']));
        }
    });
    $this->tenant = Tenant::create(['name' => 'Malacrida']);
    $this->nuova = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Nuova', 'color' => '#000', 'order' => 0, 'is_initial' => true]);
    // Stato con la vecchia impostazione "invia email al cliente".
    $this->perizia = TenantStatus::create(['tenant_id' => $this->tenant->id, 'name' => 'Perizia', 'color' => '#000', 'order' => 1, 'send_email_notification' => true]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'nome' => 'ROSSI MARIO', 'email' => 'mario@example.com']);
    $this->pratica = Pratica::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'utente_creatore_id' => $this->user->id, 'current_status_id' => $this->nuova->id]);
});

test('l\'email al cliente impostata sullo stato compare nella conferma prima di spostare', function () {
    $plans = $this->actingAs($this->user)
        ->postJson("/pratiche/{$this->pratica->id}/automations/preview", ['tenant_status_id' => $this->perizia->id])
        ->assertOk()->json('automations');

    expect($plans)->toHaveCount(1)
        ->and($plans[0]['id'])->toBe(0)
        ->and($plans[0]['recipients'][0]['email'])->toBe('mario@example.com');
});

test('"procedi senza automazioni" blocca anche l\'email di stato', function () {
    $this->actingAs($this->user)->patchJson("/pratiche/{$this->pratica->id}/status", [
        'current_status_id' => $this->perizia->id, 'skip_confirmable_automations' => true,
    ])->assertOk();

    expect($this->pratica->fresh()->current_status_id)->toBe($this->perizia->id)
        ->and(EmailLog::count())->toBe(0);
});

test('l\'email di stato esclusa nella conferma non parte; confermata va al destinatario scelto', function () {
    $this->actingAs($this->user)->patchJson("/pratiche/{$this->pratica->id}/status", [
        'current_status_id' => $this->perizia->id,
        'automation_overrides' => [0 => ['send' => false, 'recipients' => [], 'cc' => []]],
    ])->assertOk();
    expect(EmailLog::count())->toBe(0);

    $this->pratica->update(['current_status_id' => $this->nuova->id]);
    $this->actingAs($this->user)->patchJson("/pratiche/{$this->pratica->id}/status", [
        'current_status_id' => $this->perizia->id,
        'automation_overrides' => [0 => ['send' => true, 'recipients' => [['name' => 'Ufficio', 'email' => 'ufficio@example.com']], 'cc' => []]],
    ])->assertOk();
    expect(EmailLog::pluck('to_address')->all())->toBe(['ufficio@example.com']);
});
