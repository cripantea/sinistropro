<?php

namespace App\Http\Controllers;

use App\Jobs\SyncTenantMailboxJob;
use App\Mail\OutboundEmailMessage;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\EmailFirma;
use App\Models\EmailThread;
use App\Models\Pratica;
use App\Models\Tenant;
use App\Services\TenantMailerResolver;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
            'puoCollegare' => ImpostazioniEmailController::puoCollegare(),
            'daRicollegare' => (bool) $settings?->oauth_revocato_at,
            'firme' => FirmaEmailController::elenco(),
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
            'firma_id' => ['nullable', 'integer'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $lastMessage = $thread->messages()->orderByDesc('email_timestamp')->first();
        $subject = $this->replySubject($lastMessage?->subject ?? $thread->subject);

        $message = $this->sendAndStore(
            tenant: $authed->tenant,
            thread: $thread,
            to: $thread->counterpart_email,
            subject: $subject,
            praticaId: $lastMessage?->pratica_id,
            bodyHtml: nl2br(e($data['body'])).$this->firma($data['firma_id'] ?? null),
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
            'pratica_id' => ['nullable', 'integer'],
            'body' => ['required', 'string'],
            'firma_id' => ['nullable', 'integer'],
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
            praticaId: $this->praticaDelTenant($data['pratica_id'] ?? null),
            bodyHtml: nl2br(e($data['body'])).$this->firma($data['firma_id'] ?? null),
            inReplyTo: null,
            files: $request->file('attachments', []),
            userId: $authed->id,
        );

        return response()->json(['thread' => $this->threadPayload($thread->fresh()), 'message' => $message]);
    }

    /**
     * Corrispondenza del sinistro: le email scritte dal sinistro e le loro risposte, quelle con il cliente
     * e quelle che citano il numero del sinistro nell'oggetto.
     */
    public function perPratica(Pratica $pratica): JsonResponse
    {
        $pratica->loadMissing('cliente');
        $clienteEmail = strtolower(trim((string) $pratica->cliente?->email));
        $numeri = collect([$pratica->numeroCompagnia()])->filter(fn ($n) => mb_strlen((string) $n) >= 5)->values();

        $messaggi = EmailMessage::query()
            ->where(function ($q) use ($pratica, $clienteEmail, $numeri) {
                $q->where('pratica_id', $pratica->id);
                if ($clienteEmail !== '') {
                    $q->orWhereHas('thread', fn ($t) => $t->whereRaw('lower(counterpart_email) = ?', [$clienteEmail]));
                }
                foreach ($numeri as $n) {
                    $q->orWhere('subject', 'like', '%'.$n.'%');
                }
            })
            // Le email già legate a un altro sinistro restano lì.
            ->where(fn ($q) => $q->whereNull('pratica_id')->orWhere('pratica_id', $pratica->id))
            ->with('attachments')
            ->orderByDesc('email_timestamp')
            ->limit(200)
            ->get();

        $settings = TenantContext::tenant()?->mailSettings;
        $ispezione = $pratica->ispezioni()->latest()->with(['peritoContatto', 'carrozzeriaContatto'])->first();
        $destinatari = collect([
            ['label' => 'Cliente', 'email' => $pratica->cliente?->email],
            ['label' => 'Perito', 'email' => $ispezione?->peritoContatto?->email],
            ['label' => 'Carrozzeria', 'email' => $ispezione?->carrozzeriaContatto?->email],
        ])->filter(fn ($d) => filled($d['email']))->values();

        return response()->json([
            'messages' => $messaggi->map(fn (EmailMessage $m) => $this->messagePayload($m))->values(),
            'destinatari' => $destinatari,
            'oggetto' => 'Sinistro '.ltrim($pratica->numero, '#').($pratica->cliente?->nome ? ' — '.$pratica->cliente->nome : ''),
            'emailConfigured' => (bool) ($settings && $settings->is_active && $settings->host),
        ]);
    }

    private function praticaDelTenant(?int $id): ?int
    {
        if (! $id) {
            return null;
        }
        abort_unless(Pratica::whereKey($id)->exists(), 404);

        return $id;
    }

    /** HTML della firma scelta (solo tra quelle dell'utente o dell'agenzia). */
    private function firma(?int $id): string
    {
        if (! $id) {
            return '';
        }
        $firma = EmailFirma::disponibiliPer(auth()->user())->find($id);
        abort_unless($firma, 422, 'La firma scelta non esiste più: scegline un\'altra.');

        return $firma->html();
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

    /**
     * Anteprima nella pagina (immagini e PDF, anche le immagini dentro il testo delle email):
     * rimanda a un link S3 temporaneo che il browser mostra invece di scaricare.
     */
    public function viewAttachment(EmailAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->tenant_id === TenantContext::id(), 404);
        $tipo = $attachment->tipoAnteprima();
        abort_unless($tipo, 404);

        $s3Client = Storage::disk('s3')->getClient();
        $command = $s3Client->getCommand('GetObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $attachment->s3_key,
            'ResponseContentType' => strtolower($attachment->mime_type),
            'ResponseContentDisposition' => 'inline; filename="'.Str::ascii(str_replace('"', '', $attachment->filename)).'"',
            'ResponseCacheControl' => 'private, max-age=300',
        ]);

        return redirect()->away((string) $s3Client->createPresignedRequest($command, self::PRESIGNED_TTL)->getUri());
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
        ?int $praticaId = null,
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
            'pratica_id' => $praticaId,
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
            'ccAddresses' => $m->cc_addresses,
            'bodyHtml' => $m->body_html,
            // Email solo testo: prima non si vedeva nulla.
            'bodyText' => $m->body_html ? null : $m->body_text,
            'status' => $m->status,
            'praticaId' => $m->pratica_id,
            'createdAt' => $m->email_timestamp?->toIso8601String(),
            'attachments' => $m->attachments->map(fn (EmailAttachment $a) => $a->toPayload()),
        ];
    }
}
