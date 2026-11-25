<?php

use App\Models\{Cliente, Pratica, Tenant, TenantStatus, User};

// Pacchetto PDF del sinistro: mostra il numero sinistro dell'agenzia, non l'id interno.

test('il pacchetto PDF usa il numero sinistro scelto in Impostazioni, altrimenti l\'id', function () {
    $tenant = Tenant::create(['name' => 'Agenzia Pdf', 'settings' => ['custom_fields_schema' => [['name' => 'numero_sinistro', 'label' => 'N. sinistro', 'type' => 'text']]]]);
    $stato = TenantStatus::create(['tenant_id' => $tenant->id, 'name' => 'Aperto', 'color' => '#000000', 'order' => 0, 'is_initial' => true]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant-admin']);
    $cliente = Cliente::create(['tenant_id' => $tenant->id, 'nome' => 'Anna Bianchi']);
    $pratica = Pratica::create(['tenant_id' => $tenant->id, 'utente_creatore_id' => $admin->id, 'cliente_id' => $cliente->id, 'current_status_id' => $stato->id, 'custom_fields' => ['numero_sinistro' => 'SX 2026/4455']]);

    $this->actingAs($admin);
    $html = fn () => view('pdf.pratica', ['pratica' => Pratica::find($pratica->id)->load('tenant')])->render();

    expect($html())->toContain('Sinistro #'.$pratica->id);

    $tenant->update(['settings' => [...$tenant->settings, 'numero_sinistro_campo' => 'numero_sinistro']]);
    Pratica::dimenticaCampoNumero();

    expect($html())->toContain('Sinistro SX 2026/4455')->not->toContain('Sinistro #'.$pratica->id);

    $this->get("/pratiche/{$pratica->id}/export-pdf")->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=sinistro-SX-2026-4455-'.now()->format('Y-m-d').'.pdf');
});
