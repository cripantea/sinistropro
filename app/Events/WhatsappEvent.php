<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $type,
        public array $payload,
    ) {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("whatsapp.{$this->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }

    /**
     * Il worker in produzione consuma solo la coda "automations": riusiamo
     * quella invece di "default", che non ha nessun worker dedicato.
     */
    public function broadcastQueue(): string
    {
        return 'automations';
    }
}
