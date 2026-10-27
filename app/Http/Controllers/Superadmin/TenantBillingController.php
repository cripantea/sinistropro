<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Billing\StripeBilling;
use Illuminate\Http\JsonResponse;
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

    /** Fatture Stripe del cliente (caricate quando si apre la scheda Abbonamento). */
    public function fatture(Tenant $tenant, StripeBilling $billing): JsonResponse
    {
        if (! StripeBilling::configured() || ! $tenant->stripe_customer_id) {
            return response()->json(['fatture' => []]);
        }

        try {
            return response()->json(['fatture' => $billing->invoices($tenant)]);
        } catch (\Throwable $e) {
            Log::error('Stripe: lettura fatture fallita', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            return response()->json(['fatture' => [], 'errore' => 'Stripe non ha risposto: riprova tra poco.'], 502);
        }
    }

    /**
     * Gestione dell'abbonamento dal superadmin: disdetta a fine periodo, ripresa, annullamento
     * immediato, oppure rimborso dell'ultimo pagamento + annullamento (garanzia 30 giorni).
     */
    public function azione(Request $request, Tenant $tenant, StripeBilling $billing): RedirectResponse
    {
        $azione = $request->validate(['azione' => ['required', 'in:disdici,riprendi,annulla,rimborsa']])['azione'];

        abort_unless(StripeBilling::configured() && $tenant->stripe_customer_id, 422, 'Questa agenzia non ha un cliente Stripe.');
        abort_if($azione !== 'rimborsa' && ! $tenant->hasSubscription(), 422, 'Nessun abbonamento attivo.');

        try {
            $messaggio = match ($azione) {
                'disdici'  => tap('Abbonamento disdetto: resta attivo fino alla fine del periodo pagato.', fn () => $billing->cancelAtPeriodEnd($tenant, true)),
                'riprendi' => tap('Disdetta annullata: l\'abbonamento si rinnova normalmente.', fn () => $billing->cancelAtPeriodEnd($tenant, false)),
                'annulla'  => tap('Abbonamento annullato: l\'agenzia è in sola lettura.', fn () => $billing->cancelNow($tenant)),
                'rimborsa' => ($cents = $billing->refundLastAndCancel($tenant)) > 0
                    ? 'Rimborsati ' . number_format($cents / 100, 2, ',', '.') . ' € e abbonamento annullato.'
                    : 'Nessun pagamento da rimborsare; abbonamento annullato.',
            };
        } catch (\Throwable $e) {
            Log::error('Stripe: azione abbonamento fallita', ['tenant' => $tenant->id, 'azione' => $azione, 'error' => $e->getMessage()]);

            return back()->with('error', 'Stripe ha rifiutato l\'operazione: ' . $e->getMessage());
        }

        return back()->with('success', $messaggio);
    }
}
