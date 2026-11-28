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
        // Numero sinistro dell'agenzia (es. quello della compagnia) e cliente: si riconosce subito dall'oggetto.
        $cliente = trim((string) $this->pratica->cliente?->nome);

        return new Envelope(
            subject: "[{$this->pratica->tenant->name}] Sinistro {$this->pratica->numero}".($cliente !== '' ? " - {$cliente}" : '').' ancora aperto',
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
