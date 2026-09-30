<?php

use App\Models\{Tenant, User, WhatsappConversation, WhatsappMessage, WhatsappSession};
use App\Jobs\ProcessWhatsappHistoryChunkJob;
use Illuminate\Support\Facades\{Http, Event};

beforeEach(function () {
    Http::preventStrayRequests();
    $this->tenant = Tenant::create(['name' => 'Media test']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->session = WhatsappSession::create(['tenant_id' => $this->tenant->id, 'status' => 'active', 'waba_id' => '10', 'phone_number_id' => '20']);
    $conversation = WhatsappConversation::create(['tenant_id' => $this->tenant->id, 'phone_number' => '390000000001']);
    $this->message = WhatsappMessage::create(['tenant_id' => $this->tenant->id, 'whatsapp_conversation_id' => $conversation->id, 'direction' => 'inbound', 'media_type' => 'image', 'media_id' => '123']);
    config(['services.fusionwa.base_url' => 'https://fusionwa.test', 'services.fusionwa.api_key' => 'test-key', 'services.fusionwa.api_secret' => 'test-secret']);
});

test('image requires authentication', function () {
    $this->get($this->message->imageUrl())->assertRedirect('/login');
    Http::assertNothingSent();
});

test('image is inaccessible to another tenant', function () {
    $other = Tenant::create(['name' => 'Other']);
    $user = User::factory()->create(['tenant_id' => $other->id]);
    $this->actingAs($user)->get($this->message->imageUrl())->assertNotFound();
    Http::assertNothingSent();
});

test('superadmin needs tenant context', function () {
    $this->user->update(['role' => 'superadmin']);
    $this->actingAs($this->user)->get($this->message->imageUrl())->assertNotFound();
    Http::assertNothingSent();
});

test('image proxy returns validated bytes without exposing credentials', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH7sAAAAASUVORK5CYII=');
    Http::fake(['fusionwa.test/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    $response = $this->actingAs($this->user)->get($this->message->imageUrl());
    $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->getContent())->toBe($png);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    Http::assertSent(fn ($request) => $request->hasHeader('x-fusionwa-api-secret', 'test-secret')
        && $request['externalCustomerId'] === (string) $this->tenant->id
        && str_contains($request->url(), '/api/v1/media/123'));
});

test('proxy refuses invalid content and missing upstream images', function ($body, $status, $mime, $expected) {
    Http::fake(['fusionwa.test/*' => Http::response($body, $status, ['Content-Type' => $mime])]);
    $this->actingAs($this->user)->get($this->message->imageUrl())->assertStatus($expected);
})->with([
    ['<script>bad</script>', 200, 'text/html', 415],
    ['not an image', 200, 'image/png', 415],
    ['', 404, 'application/json', 404],
    ['', 500, 'application/json', 502],
]);

test('legacy messages do not expose an image URL', function () {
    $this->message->update(['media_id' => null]);
    expect($this->message->imageUrl())->toBeNull();
    $this->actingAs($this->user)->get('/whatsapp/media/'.$this->message->id)->assertNotFound();
    Http::assertNothingSent();
});

test('media metadata rejects URLs as identifiers', function () {
    expect(WhatsappMessage::mediaAttributes(['type' => 'image', 'image' => ['id' => 'https://evil.test']])['media_id'])->toBeNull();
    expect(WhatsappMessage::mediaAttributes(['type' => 'image', 'image' => ['id' => '123', 'mime_type' => 'image/jpeg']]))
        ->toBe(['media_id' => '123', 'media_mime_type' => 'image/jpeg']);
});

test('history stores image identifier and caption', function () {
    Event::fake();
    (new ProcessWhatsappHistoryChunkJob($this->session->id, ['messages' => [[
        'id' => 'wamid.history', 'from' => '390000000001', 'type' => 'image',
        'image' => ['id' => '456', 'mime_type' => 'image/jpeg', 'caption' => 'Test caption'],
    ]]]))->handle();
    $this->assertDatabaseHas('whatsapp_messages', ['wa_message_id' => 'wamid.history', 'media_id' => '456', 'body' => 'Test caption', 'source' => 'history']);
});

test('webhooks store images and captions for inbound and echo messages', function ($field, $collection, $source) {
    Event::fake();
    config(['services.whatsapp_cloud.app_secret' => 'webhook-test']);
    $payload = ['entry' => [['changes' => [['field' => $field, 'value' => [
        'metadata' => ['phone_number_id' => '20'],
        $collection => [[
            'id' => 'wamid.webhook', 'from' => '390000000001', 'to' => '390000000002',
            'type' => 'image', 'image' => ['id' => '789', 'mime_type' => 'image/jpeg', 'caption' => 'Caption'],
        ]],
    ]]]]]];
    $body = json_encode($payload);
    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-test'),
    ], $body)->assertOk();
    $this->assertDatabaseHas('whatsapp_messages', ['wa_message_id' => 'wamid.webhook', 'media_id' => '789', 'body' => 'Caption', 'source' => $source]);
    Event::assertDispatched(\App\Events\WhatsappEvent::class, fn ($event) =>
        str_starts_with($event->payload['message']['mediaUrl'] ?? '', '/whatsapp/media/'));
})->with([
    ['messages', 'messages', 'api'],
    ['smb_message_echoes', 'message_echoes', 'echo'],
]);
