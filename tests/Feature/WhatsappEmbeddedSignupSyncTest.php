<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->tenant = Tenant::create(['name' => 'Sync test']);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    config(['services.fusionwa.base_url' => 'https://fusionwa.test', 'services.fusionwa.api_key' => 'test-key', 'services.fusionwa.api_secret' => 'test-secret']);
});

function fusionWaConnected(string $wabaId, string $phoneNumberId, string $phoneNumber): array
{
    return [
        'status' => 'success',
        'customerStatus' => 'ACTIVE',
        'integrationEnabled' => true,
        'connection' => [
            'wabaId' => $wabaId,
            'phoneNumberId' => $phoneNumberId,
            'phoneNumber' => $phoneNumber,
            'status' => 'CONNECTED',
            'operational' => true,
        ],
    ];
}

test('salva la connessione solo se FusionWA la conferma, usando i valori verificati', function () {
    Http::fake(['fusionwa.test/*' => Http::response(fusionWaConnected('waba-1', 'phone-1', '+39 111'))]);

    $response = $this->actingAs($this->admin)->postJson(route('whatsapp.embedded-signup.sync'), [
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'phone_number' => '+39 111',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('whatsapp_sessions', [
        'tenant_id' => $this->tenant->id, 'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1',
        'display_phone_number' => '+39 111', 'status' => 'active',
    ]);
    Http::assertSent(fn ($request) => $request->hasHeader('x-fusionwa-api-secret', 'test-secret')
        && $request['externalCustomerId'] === (string) $this->tenant->id);
});

test('rifiuta e non salva nulla se i dati dichiarati non coincidono con FusionWA', function () {
    // L'attaccante (o un bug del frontend) dichiara un numero diverso da
    // quello che FusionWA riporta davvero per questo tenant.
    Http::fake(['fusionwa.test/*' => Http::response(fusionWaConnected('waba-vittima', 'phone-vittima', '+39 999'))]);

    $response = $this->actingAs($this->admin)->postJson(route('whatsapp.embedded-signup.sync'), [
        'waba_id' => 'waba-mio', 'phone_number_id' => 'phone-mio', 'phone_number' => '+39 111',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('whatsapp_sessions', ['tenant_id' => $this->tenant->id]);
});

test('rifiuta senza salvare se la connessione su FusionWA non è operativa', function () {
    $status = fusionWaConnected('waba-1', 'phone-1', '+39 111');
    $status['connection']['operational'] = false;
    Http::fake(['fusionwa.test/*' => Http::response($status)]);

    $response = $this->actingAs($this->admin)->postJson(route('whatsapp.embedded-signup.sync'), [
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'phone_number' => '+39 111',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('whatsapp_sessions', ['tenant_id' => $this->tenant->id]);
});

test('se FusionWA non risponde, nessuna scrittura: non è un "non connesso"', function () {
    Http::fake(['fusionwa.test/*' => Http::response(null, 500)]);

    $response = $this->actingAs($this->admin)->postJson(route('whatsapp.embedded-signup.sync'), [
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'phone_number' => '+39 111',
    ]);

    $response->assertStatus(503);
    $this->assertDatabaseMissing('whatsapp_sessions', ['tenant_id' => $this->tenant->id]);
});

test('un utente che non è tenant-admin non può collegare un numero', function () {
    $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-user']);

    $this->actingAs($other)->postJson(route('whatsapp.embedded-signup.sync'), [
        'waba_id' => 'waba-1', 'phone_number_id' => 'phone-1', 'phone_number' => '+39 111',
    ])->assertForbidden();

    Http::assertNothingSent();
});
