<?php

use App\Models\{EmailAttachment, EmailLog, EmailMessage, EmailThread, Tenant, User, WhatsappConversation, WhatsappMessage};

// L'agenzia elimina da SinistroPro email e messaggi WhatsApp (es. quelli di prova), solo i propri.

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Agenzia Ponte']);
    $this->altra = Tenant::create(['name' => 'Altra']);
    $this->utente = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
});

function emailDi(Tenant $t, EmailThread $thread, string $testo, string $quando): EmailMessage
{
    return EmailMessage::acrossAllTenants()->create([
        'tenant_id' => $t->id, 'email_thread_id' => $thread->id, 'direction' => 'outbound', 'folder' => 'sent',
        'from_address' => 'sinistri@assiponte.it', 'to_addresses' => ['c@example.com'], 'subject' => 'Prova',
        'body_html' => "<p>{$testo}</p>", 'body_text' => $testo, 'message_id' => uniqid().'@x', 'status' => 'sent',
        'email_timestamp' => $quando,
    ]);
}

test('eliminare un\'email toglie anche gli allegati e aggiorna la conversazione; l\'ultima elimina la conversazione', function () {
    $thread = EmailThread::create(['tenant_id' => $this->tenant->id, 'counterpart_email' => 'c@example.com', 'last_message_at' => now()]);
    $vecchia = emailDi($this->tenant, $thread, 'vecchia', '2026-11-25 10:00:00');
    $prova = emailDi($this->tenant, $thread, 'prova', '2026-11-26 10:00:00');
    \Illuminate\Support\Facades\Storage::fake('s3');
    \Illuminate\Support\Facades\Storage::disk('s3')->put('email/a.pdf', 'pdf');
    EmailAttachment::create(['tenant_id' => $this->tenant->id, 'email_message_id' => $prova->id, 'filename' => 'a.pdf', 's3_key' => 'email/a.pdf']);

    $this->actingAs($this->utente)->deleteJson("/email/messaggi/{$prova->id}")->assertOk();

    expect(EmailMessage::find($prova->id))->toBeNull()
        ->and(EmailAttachment::count())->toBe(0)
        ->and(\Illuminate\Support\Facades\Storage::disk('s3')->exists('email/a.pdf'))->toBeFalse()
        ->and($thread->fresh()->last_message_preview)->toBe('vecchia');

    $this->deleteJson("/email/messaggi/{$vecchia->id}")->assertOk();
    expect(EmailThread::find($thread->id))->toBeNull();
});

test('email automatica del registro eliminabile', function () {
    $log = EmailLog::registra(['tenant_id' => $this->tenant->id, 'tipo' => 'automazione', 'to_address' => 'c@example.com', 'status' => 'failed']);

    $this->actingAs($this->utente)->deleteJson('/email/automatiche/'.EmailLog::acrossAllTenants()->latest('id')->value('id'))->assertOk();
    expect(EmailLog::acrossAllTenants()->count())->toBe(0);
});

test('messaggio WhatsApp eliminabile, l\'anteprima della chat torna al precedente', function () {
    $conv = WhatsappConversation::create(['tenant_id' => $this->tenant->id, 'phone_number' => '393331112222', 'last_message_preview' => 'prova']);
    WhatsappMessage::create(['tenant_id' => $this->tenant->id, 'whatsapp_conversation_id' => $conv->id, 'direction' => 'inbound', 'body' => 'ciao', 'status' => 'received', 'wa_timestamp' => '2026-11-25 10:00:00']);
    $prova = WhatsappMessage::create(['tenant_id' => $this->tenant->id, 'whatsapp_conversation_id' => $conv->id, 'direction' => 'outbound', 'body' => 'prova', 'status' => 'sent', 'wa_timestamp' => '2026-11-26 10:00:00']);

    $this->actingAs($this->utente)->deleteJson("/whatsapp/messaggi/{$prova->id}")->assertOk();

    expect(WhatsappMessage::acrossAllTenants()->find($prova->id))->toBeNull()
        ->and($conv->fresh()->last_message_preview)->toBe('ciao');
});

test('i messaggi di un\'altra agenzia non si toccano', function () {
    $thread = EmailThread::acrossAllTenants()->create(['tenant_id' => $this->altra->id, 'counterpart_email' => 'x@example.com']);
    $email = emailDi($this->altra, $thread, 'x', '2026-11-26 10:00:00');
    $conv = WhatsappConversation::acrossAllTenants()->create(['tenant_id' => $this->altra->id, 'phone_number' => '39333']);
    $wa = WhatsappMessage::acrossAllTenants()->create(['tenant_id' => $this->altra->id, 'whatsapp_conversation_id' => $conv->id, 'direction' => 'outbound', 'body' => 'x', 'status' => 'sent']);
    EmailLog::registra(['tenant_id' => $this->altra->id, 'tipo' => 'automazione', 'status' => 'sent']);
    $log = EmailLog::acrossAllTenants()->latest('id')->value('id');

    $this->actingAs($this->utente)->deleteJson("/email/messaggi/{$email->id}")->assertNotFound();
    $this->deleteJson("/whatsapp/messaggi/{$wa->id}")->assertNotFound();
    $this->deleteJson("/email/automatiche/{$log}")->assertNotFound();

    expect(EmailMessage::acrossAllTenants()->count())->toBe(1)
        ->and(WhatsappMessage::acrossAllTenants()->count())->toBe(1)
        ->and(EmailLog::acrossAllTenants()->count())->toBe(1);
});
