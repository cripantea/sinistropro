<?php

namespace App\Events;

use App\Models\Pratica;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PraticaCampoDataAggiornato
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Pratica $pratica,
        public readonly string $fieldName,
        public readonly bool $skipConfirmableAutomations = false,
    ) {}
}
