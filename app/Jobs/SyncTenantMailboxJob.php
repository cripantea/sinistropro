<?php

namespace App\Jobs;

use App\Events\EmailEvent;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\TenantMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

class SyncTenantMailboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Nomi comuni della cartella "Inviata": i provider non concordano su
     * uno standard, quindi si prova la lista finché una risolve (soft-fail,
     * nessun crash se nessuna combacia — si logga solo un warning).
     */
    private const SENT_FOLDER_CANDIDATES = [
        'Sent', 'INBOX.Sent', 'Sent Items', 'Sent Mail',
        'Posta inviata', 'Inviata', 'INBOX.Inviata',
        '[Gmail]/Sent Mail',
    ];

    public function __construct(public readonly int $tenantId)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $settings = TenantMailSettings::where('tenant_id', $this->tenantId)->first();

        if (! $settings || ! $settings->is_active || ! $settings->imap_host) {
            return;
        }

        $client = (new ClientManager())->make([
            'host' => $settings->imap_host,
            'port' => $settings->imap_port ?: 993,
            'encryption' => $settings->imap_encryption ?: 'ssl',
            'validate_cert' => true,
            'username' => $settings->username,
            'password' => $settings->password,
        ]);

        try {
            $client->connect();
        } catch (\Throwable $e) {
            Log::error('SyncTenantMailboxJob: connessione IMAP fallita', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $imported = 0;

        try {
            $inbox = $client->getFolder('INBOX');
            if ($inbox) {
                $imported += $this->syncFolder($inbox, 'inbox', $settings);
            }

            $sentFolder = $this->resolveSentFolder($client);
            if ($sentFolder) {
                $imported += $this->syncFolder($sentFolder, 'sent', $settings);
            } else {
                Log::warning('SyncTenantMailboxJob: cartella Inviata non trovata', [
                    'tenant_id' => $this->tenantId,
                ]);
            }
        } finally {
            $client->disconnect();
        }

        if ($imported > 0) {
            broadcast(new EmailEvent($this->tenantId, 'inbox_synced', ['imported' => $imported]));
        }
    }

    private function resolveSentFolder(Client $client): ?Folder
    {
        foreach (self::SENT_FOLDER_CANDIDATES as $candidate) {
            $folder = $client->getFolderByName($candidate, true);
            if ($folder) {
                return $folder;
            }
        }

        return null;
    }

    private function syncFolder(Folder $folder, string $folderKey, TenantMailSettings $settings): int
    {
        $lastUidColumn = $folderKey === 'inbox' ? 'imap_last_uid_inbox' : 'imap_last_uid_sent';
        $lastUid = $settings->{$lastUidColumn} ?? 0;

        $messages = $folder->messages()->getByUidGreater($lastUid);

        $imported = 0;
        $maxUid = $lastUid;

        foreach ($messages as $message) {
            $maxUid = max($maxUid, $message->getUid());

            try {
                if ($this->importMessage($message, $folderKey)) {
                    $imported++;
                }
            } catch (\Throwable $e) {
                Log::error('SyncTenantMailboxJob: errore import messaggio', [
                    'tenant_id' => $this->tenantId,
                    'folder' => $folderKey,
                    'uid' => $message->getUid(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $settings->update([$lastUidColumn => $maxUid]);

        return $imported;
    }

    private function importMessage(Message $message, string $folderKey): bool
    {
        $messageId = $message->getMessageId()->first();
        $messageId = is_string($messageId) && trim($messageId) !== '' ? trim($messageId) : null;

        if ($messageId && EmailMessage::where('tenant_id', $this->tenantId)->where('message_id', $messageId)->exists()) {
            return false;
        }

        $fromAddress = $message->getFrom()->first();
        $fromEmail = $fromAddress?->mail ?? '';
        $fromName = $fromAddress?->personal !== '' ? $fromAddress?->personal : null;

        if ($fromEmail === '') {
            return false;
        }

        $toAddresses = collect($message->getTo()->all())->map(fn ($a) => $a->mail)->filter()->values()->all();
        $ccAddresses = collect($message->getCc()->all())->map(fn ($a) => $a->mail)->filter()->values()->all();

        // Come per gli echo WhatsApp: nella cartella Inviata il "counterpart"
        // della conversazione è il destinatario, non il mittente (che siamo noi).
        $direction = $folderKey === 'sent' ? 'outbound' : 'inbound';
        $counterpartEmail = $direction === 'outbound' ? ($toAddresses[0] ?? null) : $fromEmail;
        $counterpartName = $direction === 'outbound' ? null : $fromName;

        if (! $counterpartEmail) {
            return false;
        }

        $subject = (string) ($message->getSubject()->first() ?? '(nessun oggetto)');

        try {
            $timestamp = $message->getDate()->toDate();
        } catch (\Throwable) {
            $timestamp = now();
        }

        $bodyHtmlRaw = $message->getHTMLBody();
        $bodyHtml = $bodyHtmlRaw ? Purifier::clean($bodyHtmlRaw) : null;
        $bodyText = $message->getTextBody() ?: null;

        $thread = EmailThread::firstOrCreate(
            ['tenant_id' => $this->tenantId, 'counterpart_email' => $counterpartEmail],
            ['counterpart_name' => $counterpartName, 'subject' => $subject]
        );

        $inReplyTo = $message->getInReplyTo()->first();

        $emailMessage = EmailMessage::create([
            'tenant_id' => $this->tenantId,
            'email_thread_id' => $thread->id,
            'direction' => $direction,
            'folder' => $folderKey,
            'from_address' => $fromEmail,
            'from_name' => $fromName,
            'to_addresses' => $toAddresses,
            'cc_addresses' => $ccAddresses ?: null,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'message_id' => $messageId,
            'in_reply_to' => is_string($inReplyTo) && $inReplyTo !== '' ? $inReplyTo : null,
            'status' => $direction === 'outbound' ? 'sent' : 'received',
            'email_timestamp' => $timestamp,
        ]);

        $this->importAttachments($message, $emailMessage);

        $threadUpdates = [];
        if (! $thread->last_message_at || $timestamp->gt($thread->last_message_at)) {
            $threadUpdates['last_message_at'] = $timestamp;
            $threadUpdates['last_message_preview'] = Str::limit(trim(strip_tags((string) ($bodyText ?: $bodyHtml ?: ''))), 200);
            $threadUpdates['subject'] = $subject;
        }
        if ($direction === 'inbound') {
            $threadUpdates['unread_count'] = $thread->unread_count + 1;
        }
        if ($threadUpdates !== []) {
            $thread->update($threadUpdates);
        }

        broadcast(new EmailEvent($this->tenantId, 'message', [
            'thread' => [
                'id' => $thread->id,
                'counterpartEmail' => $thread->counterpart_email,
                'counterpartName' => $thread->counterpart_name,
                'subject' => $thread->subject,
                'lastMessagePreview' => $thread->last_message_preview,
                'lastMessageAt' => $thread->last_message_at?->toIso8601String(),
                'unreadCount' => $thread->unread_count,
            ],
            'message' => [
                'id' => $emailMessage->id,
                'direction' => $direction,
                'fromAddress' => $fromEmail,
                'fromName' => $fromName,
                'subject' => $subject,
                'bodyHtml' => $bodyHtml,
                'status' => $emailMessage->status,
                'createdAt' => $emailMessage->email_timestamp?->toIso8601String(),
                'attachments' => $emailMessage->attachments()->get()->map(fn ($a) => [
                    'id' => $a->id,
                    'filename' => $a->filename,
                    'mimeType' => $a->mime_type,
                    'size' => $a->size,
                ])->all(),
            ],
        ]));

        return true;
    }

    private function importAttachments(Message $message, EmailMessage $emailMessage): void
    {
        foreach ($message->getAttachments() as $attachment) {
            $filename = $attachment->name ?: ($attachment->filename ?: 'allegato');
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $content = $attachment->content ?? '';

            $s3Key = sprintf(
                'tenant_%d/email/%s%s',
                $this->tenantId,
                (string) Str::uuid(),
                $extension ? '.'.$extension : ''
            );

            Storage::disk('s3')->put($s3Key, $content, 'private');

            EmailAttachment::create([
                'tenant_id' => $this->tenantId,
                'email_message_id' => $emailMessage->id,
                'filename' => $filename,
                'mime_type' => $attachment->getMimeType(),
                'size' => is_string($content) ? strlen($content) : null,
                's3_key' => $s3Key,
            ]);
        }
    }
}
