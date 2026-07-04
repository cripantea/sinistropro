<?php

namespace App\Mail;

use App\Models\Pratica;
use App\Models\TenantStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PraticaStatoAggiornatoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Pratica      $pratica,
        public readonly TenantStatus $nuovoStato,
        public readonly string       $emailCliente,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Aggiornamento pratica #{$this->pratica->id} — {$this->nuovoStato->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pratica-stato-aggiornato',
            with: [
                'urlPratica' => url('/pratiche/' . $this->pratica->id),
            ],
        );
    }
}
