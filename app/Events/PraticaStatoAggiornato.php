<?php

namespace App\Events;

use App\Models\Pratica;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PraticaStatoAggiornato
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Pratica $pratica,
        public readonly ?int    $oldStatusId,
        public readonly int     $newStatusId,
    ) {}
}
