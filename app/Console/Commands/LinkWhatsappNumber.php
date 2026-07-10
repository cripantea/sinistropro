<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\WhatsappSession;
use Illuminate\Console\Command;

class LinkWhatsappNumber extends Command
{
    protected $signature   = 'whatsapp:link-number {tenant_id} {phone_number_id} {display_phone_number}';
    protected $description = 'Collega un numero WhatsApp Cloud API (già registrato su WhatsApp Manager) a un tenant';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant {$tenantId} non trovato.");
            return self::FAILURE;
        }

        WhatsappSession::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'phone_number_id'       => $this->argument('phone_number_id'),
                'display_phone_number'  => $this->argument('display_phone_number'),
                'status'                => 'active',
                'last_connected_at'     => now(),
            ]
        );

        $this->info("Numero collegato al tenant \"{$tenant->name}\".");
        return self::SUCCESS;
    }
}
