<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Billing\StripeBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantBillingController extends Controller
{
    /**
     * Modalità (Stripe o fatturato a parte), prezzi personalizzati in euro IVA inclusa
     * (vuoto = standard), costo di attivazione e accesso gratuito fino a una data.
     */
    public function update(Request $request, Tenant $tenant, StripeBilling $billing): RedirectResponse
    {
        $data = $request->validate([
            'billing_mode'  => ['required', 'in:stripe,esterno'],
            'price_monthly' => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'price_yearly'  => ['nullable', 'numeric', 'min:1', 'max:1000000'],
            'setup_fee'     => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'free_until'    => ['nullable', 'date'],
        ]);

        $cents = fn (?string $euro) => $euro === null || $euro === '' ? null : (int) round(((float) $euro) * 100);

        $tenant->forceFill([
            'billing_mode'        => $data['billing_mode'],
            'price_monthly_cents' => $cents($data['price_monthly'] ?? null),
            'price_yearly_cents'  => $cents($data['price_yearly'] ?? null),
            'setup_fee_cents'     => $cents($data['setup_fee'] ?? null) ?: null,
            'free_until'          => $data['free_until'] ?? null,
        ]);

        $pricesChanged = $tenant->isDirty(['price_monthly_cents', 'price_yearly_cents']);
        $tenant->save();

        // Chi paga già passa al nuovo prezzo dal prossimo rinnovo.
        if ($pricesChanged && $tenant->hasSubscription() && StripeBilling::configured()) {
            try {
                $billing->applyCurrentPrice($tenant);
            } catch (\Throwable $e) {
                Log::error('Stripe: aggiornamento prezzo abbonamento fallito', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

                return back()->with('error', 'Prezzo salvato, ma Stripe non ha aggiornato l\'abbonamento in corso: ' . $e->getMessage());
            }

            return back()->with('success', 'Abbonamento salvato. Il nuovo prezzo vale dal prossimo rinnovo.');
        }

        return back()->with('success', 'Abbonamento salvato.');
    }
}
