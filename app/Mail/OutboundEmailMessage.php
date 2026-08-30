<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable generica per risposte/nuovi messaggi composti dal client email
 * della webapp — a differenza delle mailable esistenti (tutte legate a un
 * template Blade per notifiche di sistema), qui il corpo è HTML libero
 * scritto dall'utente.
 */
class OutboundEmailMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>  $uploadedFiles
     */
    public function __construct(
        public readonly string $emailSubject,
        public readonly string $bodyHtml,
        public readonly string $messageIdValue,
        public readonly ?string $inReplyTo = null,
        public readonly array $uploadedFiles = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->bodyHtml);
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: $this->messageIdValue,
            // In-Reply-To/References: senza questi header la risposta arriva al
            // destinatario come email slegata invece che nello stesso thread.
            references: $this->inReplyTo ? [$this->inReplyTo] : [],
            text: $this->inReplyTo ? ['In-Reply-To' => "<{$this->inReplyTo}>"] : [],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return collect($this->uploadedFiles)
            ->map(fn ($file) => Attachment::fromData(fn () => $file->getContent(), $file->getClientOriginalName()))
            ->all();
    }
}
