<?php

namespace App\Mail;

use App\Models\Pratica;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvvisoPraticaAperta extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Pratica $pratica,
        public readonly User    $destinatario,
        public readonly Carbon  $nuovaDataAvviso,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->pratica->tenant->name}] Avviso sinistro #{$this->pratica->id} ancora aperto",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.avviso-pratica-aperta',
            with: [
                'urlPratica' => url('/pratiche/' . $this->pratica->id),
            ],
        );
    }
}
