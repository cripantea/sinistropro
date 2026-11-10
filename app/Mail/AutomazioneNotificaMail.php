<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\EmailFirma;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class AutomazioneNotificaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $compiledBody,
        public readonly string $tenantName,
        public readonly array  $documentLinks = [],
        public readonly ?EmailFirma $firma = null,
    ) {
        // Immagine della firma incorporata nell'email (src="cid:…").
        $this->withSymfonyMessage(function (Email $email): void {
            if ($this->firma?->immagine && ($dati = $this->firma->contenutoImmagine())) {
                $email->addPart((new DataPart($dati, 'firma.png', $this->firma->immagine_mime ?: 'image/png'))->asInline()->setContentId($this->firma->cid()));
            }
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.automazione-notifica');
    }
}
