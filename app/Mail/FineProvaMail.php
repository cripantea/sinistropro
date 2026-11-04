<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Promemoria 7 giorni prima della fine della prova gratuita (lo chiedono le regole dei circuiti carte). */
class FineProvaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Tenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SinistroPro: la tua prova gratuita finisce il ' . $this->tenant->subscription_ends_at->format('d/m/Y'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.fine-prova', with: [
            'fine'   => $this->tenant->subscription_ends_at->format('d/m/Y'),
            'prezzo' => number_format($this->tenant->priceCents($this->tenant->subscription_interval === 'year' ? 'year' : 'month') / 100, 0, ',', '.'),
            'unita'  => $this->tenant->subscription_interval === 'year' ? 'anno' : 'mese',
        ]);
    }
}
