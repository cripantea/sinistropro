<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Billing\StripeBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Abbonamento del tenant: pagina in app (/abbonamento) e link di pagamento senza login
 * (/paga/{tenant}, firmato) che il superadmin manda al cliente dopo la demo.
 * Pagare e gestire l'abbonamento è riservato all'amministratore dell'agenzia.
 */
class AbbonamentoController extends Controller
{
    public function show(Request $request): Response
    {
        $tenant = $this->tenant($request);

        return Inertia::render('Abbonamento/Index', [
            'billing'  => $tenant->billingSummary(),
            'canManage' => $request->user()->isTenantAdmin(),
            'stripeReady' => StripeBilling::configured(),
        ]);
    }

    public function checkout(Request $request, StripeBilling $billing): HttpResponse
    {
        $tenant = $this->tenantAdmin($request);
        $interval = $request->validate(['interval' => ['required', 'in:month,year']])['interval'];

        if ($tenant->hasSubscription()) {
            return redirect()->route('abbonamento')->with('error', 'L\'abbonamento è già attivo.');
        }

        return $this->toStripe(fn () => $billing->checkoutUrl(
            $tenant,
            $interval,
            route('abbonamento.attivo'),
            route('abbonamento'),
            $request->user()->email,
        ));
    }

    /** Ritorno dal Checkout: attiva subito, senza aspettare il webhook. */
    public function attivo(Request $request, StripeBilling $billing): RedirectResponse
    {
        $tenant = $this->tenant($request);

        if ($request->filled('session_id') && StripeBilling::configured()) {
            try {
                $billing->completeCheckout($tenant, (string) $request->string('session_id'));
            } catch (\Throwable $e) {
                Log::warning('Stripe: verifica sessione al ritorno fallita', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
            }
        }

        return $tenant->fresh()->hasAccess()
            ? redirect()->route('abbonamento')->with('success', 'Abbonamento attivo. Grazie!')
            : redirect()->route('abbonamento')->with('success', 'Pagamento ricevuto: l\'attivazione richiede qualche secondo, ricarica la pagina tra poco.');
    }

    public function annuale(Request $request, StripeBilling $billing): RedirectResponse
    {
        $tenant = $this->tenantAdmin($request);

        if (! $tenant->hasSubscription() || $tenant->subscription_interval !== 'month') {
            return back()->with('error', 'Il passaggio all\'annuale è possibile solo da un abbonamento mensile attivo.');
        }

        try {
            $billing->switchToYearly($tenant);
        } catch (\Throwable $e) {
            Log::error('Stripe: passaggio ad annuale fallito', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Non siamo riusciti a completare il passaggio (pagamento non riuscito?). Controlla la carta in "Gestisci pagamento" e riprova.');
        }

        return back()->with('success', 'Fatto: ora l\'abbonamento è annuale. Il credito del mese già pagato è stato scalato.');
    }

    public function portale(Request $request, StripeBilling $billing): HttpResponse
    {
        $tenant = $this->tenantAdmin($request);

        if (! $tenant->stripe_customer_id) {
            return back()->with('error', 'Nessun abbonamento da gestire.');
        }

        return $this->toStripe(fn () => $billing->portalUrl($tenant, route('abbonamento')));
    }

    // ── Link di pagamento senza login ──────────────────────────────────────

    public function paga(Request $request, Tenant $tenant): Response
    {
        return Inertia::render('Abbonamento/Paga', [
            'tenantName'  => $tenant->name,
            'billing'     => $tenant->billingSummary(),
            'stripeReady' => StripeBilling::configured(),
            'checkoutUrl' => URL::signedRoute('paga.checkout', $tenant),
        ]);
    }

    public function pagaCheckout(Request $request, Tenant $tenant, StripeBilling $billing): HttpResponse
    {
        $interval = $request->validate(['interval' => ['required', 'in:month,year']])['interval'];

        if ($tenant->isBilledExternally() || $tenant->hasSubscription()) {
            return redirect(URL::signedRoute('paga', $tenant));
        }

        return $this->toStripe(fn () => $billing->checkoutUrl(
            $tenant,
            $interval,
            URL::signedRoute('paga.grazie', $tenant),
            URL::signedRoute('paga', $tenant),
            $tenant->users()->where('role', 'tenant-admin')->orderBy('id')->value('email'),
        ));
    }

    public function pagaGrazie(Request $request, Tenant $tenant, StripeBilling $billing): Response
    {
        // session_id lo aggiunge Stripe dopo la firma: va escluso dalla verifica.
        abort_unless($request->hasValidSignatureWhileIgnoring(['session_id']), 403);

        if ($request->filled('session_id') && StripeBilling::configured()) {
            try {
                $billing->completeCheckout($tenant, (string) $request->string('session_id'));
            } catch (\Throwable $e) {
                Log::warning('Stripe: verifica sessione al ritorno fallita', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
            }
        }

        return Inertia::render('Abbonamento/Grazie', [
            'tenantName' => $tenant->name,
            'attivo'     => $tenant->fresh()->hasAccess(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant && ! $tenant->isBilledExternally(), 404);

        return $tenant;
    }

    private function tenantAdmin(Request $request): Tenant
    {
        abort_unless($request->user()->isTenantAdmin(), 403, 'Solo l\'amministratore dell\'agenzia può gestire l\'abbonamento.');

        return $this->tenant($request);
    }

    /** Redirect a una pagina Stripe; se Stripe non risponde, torna indietro con un messaggio. */
    private function toStripe(callable $url): HttpResponse
    {
        if (! StripeBilling::configured()) {
            return back()->with('error', 'Pagamento non ancora disponibile: riprova tra poco.');
        }

        try {
            return Inertia::location($url());
        } catch (\Throwable $e) {
            Log::error('Stripe: pagina di pagamento non disponibile', ['error' => $e->getMessage()]);

            return back()->with('error', 'Al momento non riusciamo ad aprire il pagamento. Riprova tra qualche minuto o scrivici.');
        }
    }
}
