<?php

namespace App\Console\Commands;

use App\Jobs\SyncTenantMailboxJob;
use App\Models\TenantMailSettings;
use Illuminate\Console\Command;

class SyncMailboxes extends Command
{
    protected $signature = 'app:sync-mailboxes';

    protected $description = 'Dispatcha un job di sync IMAP (INBOX + Inviata) per ogni tenant con casella email attiva.';

    public function handle(): int
    {
        TenantMailSettings::query()
            ->where('is_active', true)
            ->whereNotNull('imap_host')
            ->pluck('tenant_id')
            ->each(fn (int $tenantId) => SyncTenantMailboxJob::dispatch($tenantId));

        return self::SUCCESS;
    }
}
