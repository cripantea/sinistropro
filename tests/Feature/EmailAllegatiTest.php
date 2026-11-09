<?php

use App\Jobs\SyncTenantMailboxJob;
use App\Models\{EmailAttachment, EmailMessage, EmailThread, Tenant, User};

// Email leggibili: immagini dentro il testo, tabelle, email solo testo e anteprima allegati.

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Agenzia Email']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant-admin']);
    $thread = EmailThread::create(['tenant_id' => $this->tenant->id, 'counterpart_email' => 'perito@studio.it']);
    $this->msg = EmailMessage::create([
        'tenant_id' => $this->tenant->id, 'email_thread_id' => $thread->id, 'direction' => 'inbound', 'folder' => 'inbox',
        'from_address' => 'perito@studio.it', 'to_addresses' => ['sinistri@agenzia.it'], 'subject' => 'Perizia',
        'body_text' => "Buongiorno,\nin allegato la perizia.", 'email_timestamp' => now(),
    ]);
    $this->foto = EmailAttachment::create(['tenant_id' => $this->tenant->id, 'email_message_id' => $this->msg->id, 'filename' => 'logo.png', 'mime_type' => 'image/png', 's3_key' => 'k1', 'content_id' => 'logo@studio']);
    $this->pdf = EmailAttachment::create(['tenant_id' => $this->tenant->id, 'email_message_id' => $this->msg->id, 'filename' => 'perizia.pdf', 'mime_type' => 'application/pdf', 's3_key' => 'k2']);
    $this->zip = EmailAttachment::create(['tenant_id' => $this->tenant->id, 'email_message_id' => $this->msg->id, 'filename' => 'foto.zip', 'mime_type' => 'application/zip', 's3_key' => 'k3']);
});

test('le immagini dentro il testo puntano all\'allegato e spariscono dalla lista; le tabelle restano', function () {
    $html = SyncTenantMailboxJob::pulisciHtml(
        '<table><tr><td style="padding:4px">Targa</td><td>AB123CD</td></tr></table><img src="cid:logo@studio"><img src="cid:sconosciuto"><script>x</script>',
        $this->msg
    );

    expect($html)->toContain('<table>')->toContain('AB123CD')
        ->toContain('/email/attachments/'.$this->foto->id.'/view')
        ->not->toContain('script')->not->toContain('sconosciuto');
    expect($this->foto->fresh()->is_inline)->toBeTrue();
});

test('i messaggi mandano il testo semplice se manca l\'HTML e il tipo di anteprima degli allegati', function () {
    $this->actingAs($this->user)->getJson("/email/threads/{$this->msg->email_thread_id}/messages")
        ->assertOk()
        ->assertJsonPath('messages.0.bodyText', "Buongiorno,\nin allegato la perizia.")
        ->assertJsonPath('messages.0.attachments.0.preview', 'image')
        ->assertJsonPath('messages.0.attachments.1.preview', 'pdf')
        ->assertJsonPath('messages.0.attachments.1.viewUrl', '/email/attachments/'.$this->pdf->id.'/view')
        ->assertJsonPath('messages.0.attachments.2.preview', null)
        ->assertJsonPath('messages.0.attachments.2.viewUrl', null);
});

test('l\'anteprima è solo per foto e PDF e solo per la propria agenzia', function () {
    $this->actingAs($this->user)->get("/email/attachments/{$this->zip->id}/view")->assertNotFound();

    $altro = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Altra'])->id]);
    $this->actingAs($altro)->get("/email/attachments/{$this->pdf->id}/view")->assertNotFound();
});
