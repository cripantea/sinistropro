<?php

namespace App\Console\Commands;

use App\Mail\FineProvaMail;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Ogni giorno: email all'amministratore delle agenzie in prova che finisce entro 7 giorni.
 * Una volta sola per prova (settings.promemoria_fine_prova = data di fine già avvisata).
 */
class PromemoriaFineProva extends Command
{
    protected $signature = 'app:promemoria-fine-prova';

    protected $description = 'Avvisa 7 giorni prima della fine della prova gratuita';

    public function handle(): int
    {
        $tenants = Tenant::where('billing_mode', 'stripe')
            ->where('subscription_status', 'trialing')
            ->where('subscription_cancel_at_period_end', false)
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<=', now()->addDays(7)->endOfDay())
            ->where('subscription_ends_at', '>', now())
            ->get();

        foreach ($tenants as $tenant) {
            $fine = $tenant->subscription_ends_at->toDateString();
            if ($tenant->getSetting('promemoria_fine_prova') === $fine || $tenant->getSetting('demo')) {
                continue;
            }

            $email = $tenant->users()->where('role', 'tenant-admin')->where('is_active', true)->orderBy('id')->value('email');
            if (! $email) {
                continue;
            }

            try {
                Mail::to($email)->send(new FineProvaMail($tenant));
            } catch (\Throwable $e) {
                Log::error('Promemoria fine prova non inviato', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
                continue;
            }

            $tenant->forceFill(['settings' => [...($tenant->settings ?? []), 'promemoria_fine_prova' => $fine]])->save();
            $this->info("Promemoria fine prova: {$tenant->name} ({$email})");
        }

        return self::SUCCESS;
    }
}
