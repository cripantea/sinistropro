<?php

namespace App\Http\Controllers;

use App\Jobs\SyncTenantMailboxJob;
use App\Mail\OutboundEmailMessage;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\Tenant;
use App\Services\TenantMailerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EmailController extends Controller
{
    private const PRESIGNED_TTL = '+5 minutes';

    public function __construct(private readonly TenantMailerResolver $mailer)
    {
    }

    public function index(): InertiaResponse
    {
        $settings = auth()->user()->tenant->mailSettings;

        return Inertia::render('Email/Index', [
            'emailConfigured' => (bool) ($settings && $settings->is_active && $settings->imap_host),
        ]);
    }

    public function threads(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $threads = EmailThread::where('tenant_id', $tenantId)
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->get()
            ->map(fn (EmailThread $t) => $this->threadPayload($t));

        return response()->json(['threads' => $threads]);
    }

    /**
     * Sync IMAP immediato su richiesta dell'utente — non sostituisce il
     * poller schedulato ogni 2 minuti, gira in aggiunta per chi non vuole
     * aspettare.
     */
    public function sync(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        SyncTenantMailboxJob::dispatchSync($tenantId);

        $threads = EmailThread::where('tenant_id', $tenantId)
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->get()
            ->map(fn (EmailThread $t) => $this->threadPayload($t));

        return response()->json(['threads' => $threads]);
    }

    public function messages(EmailThread $thread): JsonResponse
    {
        abort_unless($thread->tenant_id === auth()->user()->tenant_id, 403);

        $thread->update(['unread_count' => 0]);

        $messages = $thread->messages()
            ->with('attachments')
            ->orderBy('email_timestamp')
            ->get()
            ->map(fn (EmailMessage $m) => $this->messagePayload($m));

        return response()->json(['messages' => $messages]);
    }

    public function reply(Request $request, EmailThread $thread): JsonResponse
    {
        $authed = auth()->user();
        abort_unless($thread->tenant_id === $authed->tenant_id, 403);

        $data = $request->validate([
            'body' => ['required', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $lastMessage = $thread->messages()->orderByDesc('email_timestamp')->first();
        $subject = $this->replySubject($lastMessage?->subject ?? $thread->subject);

        $message = $this->sendAndStore(
            tenant: $authed->tenant,
            thread: $thread,
            to: $thread->counterpart_email,
            subject: $subject,
            bodyHtml: nl2br(e($data['body'])),
            inReplyTo: $lastMessage?->message_id,
            files: $request->file('attachments', []),
            userId: $authed->id,
        );

        return response()->json(['message' => $message]);
    }

    public function compose(Request $request): JsonResponse
    {
        $authed = auth()->user();

        $data = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $thread = EmailThread::firstOrCreate(
            ['tenant_id' => $authed->tenant_id, 'counterpart_email' => $data['to']],
            ['subject' => $data['subject']]
        );

        $message = $this->sendAndStore(
            tenant: $authed->tenant,
            thread: $thread,
            to: $data['to'],
            subject: $data['subject'],
            bodyHtml: nl2br(e($data['body'])),
            inReplyTo: null,
            files: $request->file('attachments', []),
            userId: $authed->id,
        );

        return response()->json(['thread' => $this->threadPayload($thread->fresh()), 'message' => $message]);
    }

    public function downloadAttachment(EmailAttachment $attachment): JsonResponse
    {
        abort_unless($attachment->tenant_id === auth()->user()->tenant_id, 403);

        $s3Client = Storage::disk('s3')->getClient();
        $command = $s3Client->getCommand('GetObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $attachment->s3_key,
        ]);

        $url = (string) $s3Client->createPresignedRequest($command, self::PRESIGNED_TTL)->getUri();

        return response()->json(['url' => $url]);
    }

    private function replySubject(?string $originalSubject): string
    {
        $originalSubject = $originalSubject ?: '(nessun oggetto)';

        return str_starts_with(strtolower($originalSubject), 're:')
            ? $originalSubject
            : "Re: {$originalSubject}";
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function sendAndStore(
        Tenant $tenant,
        EmailThread $thread,
        string $to,
        string $subject,
        string $bodyHtml,
        ?string $inReplyTo,
        array $files,
        int $userId,
    ): array {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $messageIdValue = Str::uuid()."@{$host}";

        $mailable = new OutboundEmailMessage($subject, $bodyHtml, $messageIdValue, $inReplyTo, $files);

        $status = 'sent';
        try {
            $this->mailer->send($tenant, $to, $mailable);
        } catch (\Throwable $e) {
            Log::error('EmailController: invio email fallito', [
                'tenant_id' => $tenant->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            $status = 'failed';
        }

        $settings = $tenant->mailSettings;
        $now = now();

        $emailMessage = EmailMessage::create([
            'tenant_id' => $tenant->id,
            'email_thread_id' => $thread->id,
            'user_id' => $userId,
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_address' => $settings?->from_address ?? '',
            'from_name' => $settings?->from_name,
            'to_addresses' => [$to],
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => trim(strip_tags($bodyHtml)),
            'message_id' => $messageIdValue,
            'in_reply_to' => $inReplyTo,
            'status' => $status,
            'email_timestamp' => $now,
        ]);

        foreach ($files as $file) {
            $this->storeAttachment($file, $emailMessage);
        }

        $thread->update([
            'last_message_at' => $now,
            'last_message_preview' => Str::limit(strip_tags($bodyHtml), 200),
            'subject' => $subject,
        ]);

        return $this->messagePayload($emailMessage->load('attachments'));
    }

    private function storeAttachment(UploadedFile $file, EmailMessage $message): void
    {
        $extension = $file->getClientOriginalExtension();
        $s3Key = sprintf(
            'tenant_%d/email/%s%s',
            $message->tenant_id,
            (string) Str::uuid(),
            $extension ? '.'.$extension : ''
        );

        Storage::disk('s3')->put($s3Key, $file->getContent(), 'private');

        EmailAttachment::create([
            'tenant_id' => $message->tenant_id,
            'email_message_id' => $message->id,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            's3_key' => $s3Key,
        ]);
    }

    private function threadPayload(EmailThread $t): array
    {
        return [
            'id' => $t->id,
            'counterpartEmail' => $t->counterpart_email,
            'counterpartName' => $t->counterpart_name,
            'subject' => $t->subject,
            'lastMessagePreview' => $t->last_message_preview,
            'lastMessageAt' => $t->last_message_at?->toIso8601String(),
            'unreadCount' => $t->unread_count,
        ];
    }

    private function messagePayload(EmailMessage $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'fromAddress' => $m->from_address,
            'fromName' => $m->from_name,
            'toAddresses' => $m->to_addresses,
            'subject' => $m->subject,
            'bodyHtml' => $m->body_html,
            'status' => $m->status,
            'createdAt' => $m->email_timestamp?->toIso8601String(),
            'attachments' => $m->attachments->map(fn (EmailAttachment $a) => [
                'id' => $a->id,
                'filename' => $a->filename,
                'mimeType' => $a->mime_type,
                'size' => $a->size,
            ]),
        ];
    }
}
