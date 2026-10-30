<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * Abbonamento SinistroPro su Stripe (account condiviso Fusion Media).
 *
 * Prezzi per cliente: non si crea un prezzo a mano per ogni tenant. Per ogni coppia
 * importo/intervallo esiste un solo Price Stripe (lookup_key sinistropro_month_9900), creato
 * la prima volta che serve: due clienti a 79 €/mese condividono lo stesso prezzo.
 */
class StripeBilling
{
    public function __construct(private StripeClient $stripe) {}

    /** Id Stripe in cache separati per modalità test e live: passando da una chiave all'altra non si usano id dell'altra modalità. */
    private function cacheKey(string $key): string
    {
        $mode = str_starts_with((string) config('services.stripe.secret'), 'sk_live') || str_starts_with((string) config('services.stripe.secret'), 'rk_live') ? 'live' : 'test';

        return "stripe_{$mode}_{$key}";
    }

    public static function configured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * Sessione Checkout per abbonarsi. Con un costo di attivazione da pagare lo aggiunge alla prima fattura.
     */
    public function checkoutUrl(Tenant $tenant, string $interval, string $successUrl, string $cancelUrl, ?string $email = null): string
    {
        $app = config('billing.stripe_app');
        $lineItems = [['price' => $this->priceId($tenant->priceCents($interval), $interval), 'quantity' => 1]];

        if ($fee = $tenant->pendingSetupFeeCents()) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => config('billing.currency'),
                    'unit_amount'  => $fee,
                    'tax_behavior' => 'inclusive',
                    'product'      => $this->productId(),
                ],
                'quantity' => 1,
            ];
        }

        $session = $this->stripe->checkout->sessions->create([
            'mode'                => 'subscription',
            'line_items'          => $lineItems,
            'client_reference_id' => (string) $tenant->id,
            'metadata'            => ['app' => $app, 'tenant_id' => $tenant->id, 'setup_fee_cents' => $tenant->pendingSetupFeeCents()],
            'subscription_data'   => ['metadata' => ['app' => $app, 'tenant_id' => $tenant->id]],
            ...($tenant->stripe_customer_id
                ? ['customer' => $tenant->stripe_customer_id, 'customer_update' => ['name' => 'auto', 'address' => 'auto']]
                : array_filter(['customer_email' => $email])),
            // Dati per la fattura elettronica (Stripe non la manda allo SDI: si fa a parte).
            'billing_address_collection' => 'required',
            'tax_id_collection'          => ['enabled' => true],
            'custom_fields'              => [
                [
                    'key'      => 'sdi',
                    'label'    => ['type' => 'custom', 'custom' => 'Codice destinatario o PEC'],
                    'type'     => 'text',
                    'optional' => true,
                    'text'     => ['maximum_length' => 100],
                ],
                [
                    'key'      => 'codice_fiscale',
                    'label'    => ['type' => 'custom', 'custom' => 'Codice fiscale (se diverso dalla P.IVA)'],
                    'type'     => 'text',
                    'optional' => true,
                    'text'     => ['minimum_length' => 11, 'maximum_length' => 16],
                ],
            ],
            'locale'                => 'it',
            'allow_promotion_codes' => true,
            'success_url'           => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'            => $cancelUrl,
        ]);

        return $session->url;
    }

    /**
     * Ritorno dal Checkout: attiva subito senza aspettare il webhook. False se la sessione non è di questo tenant.
     */
    public function completeCheckout(Tenant $tenant, string $sessionId): bool
    {
        $session = $this->stripe->checkout->sessions->retrieve($sessionId);

        if (($session->metadata->app ?? null) !== config('billing.stripe_app')
            || $session->client_reference_id !== (string) $tenant->id
            || $session->status !== 'complete') {
            return false;
        }

        $this->applyCheckout($tenant, $session);

        return true;
    }

    /** Dati da un checkout completato (ritorno del browser o webhook: può arrivare due volte). */
    public function applyCheckout(Tenant $tenant, object $session): void
    {
        // Un checkout vecchio (link di ritorno riaperto, webhook riconsegnato) non deve
        // sostituire un abbonamento diverso ancora attivo.
        if ($tenant->hasSubscription() && ($session->subscription ?? null) && $tenant->stripe_subscription_id !== $session->subscription) {
            Log::info('Stripe: checkout ignorato, il tenant ha già un abbonamento attivo diverso', ['tenant' => $tenant->id]);

            return;
        }

        $tenant->forceFill([
            'stripe_customer_id'     => $session->customer ?? $tenant->stripe_customer_id,
            'stripe_subscription_id' => $session->subscription ?? $tenant->stripe_subscription_id,
            'billing_details'        => self::billingDetails($session) ?? $tenant->billing_details,
        ]);

        if ((int) ($session->metadata->setup_fee_cents ?? 0) > 0 && ! $tenant->setup_fee_paid_at) {
            $tenant->setup_fee_paid_at = now();
        }

        $tenant->save();

        if ($tenant->stripe_subscription_id) {
            $this->syncSubscription($tenant, $this->stripe->subscriptions->retrieve($tenant->stripe_subscription_id));
        }

        Log::info('Stripe: checkout SinistroPro completato', ['tenant' => $tenant->id]);
    }

    /**
     * Copia sul tenant lo stato dell'abbonamento Stripe. Se il tenant torna ad avere accesso
     * dopo un periodo senza, gli avvisi rimasti indietro si distribuiscono su più giorni
     * invece di partire tutti insieme.
     */
    public function syncSubscription(Tenant $tenant, object $subscription): void
    {
        $hadAccess = $tenant->hasAccess();
        $item = $subscription->items->data[0] ?? null;
        // Dalle API 2025-03 il periodo è sulla voce, prima sull'abbonamento.
        $periodEnd = $item->current_period_end ?? $subscription->current_period_end ?? null;

        $tenant->forceFill([
            'stripe_subscription_id'            => $subscription->id,
            'stripe_customer_id'                => $subscription->customer ?? $tenant->stripe_customer_id,
            'subscription_status'               => $subscription->status,
            'subscription_interval'             => $item->price->recurring->interval ?? $tenant->subscription_interval,
            'subscription_ends_at'              => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
            'subscription_cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false) || ! empty($subscription->cancel_at),
        ])->save();

        if (! $hadAccess && $tenant->hasAccess()) {
            Artisan::call('app:scagliona-avvisi', ['--tenant' => $tenant->id]);
        }
    }

    /**
     * Dopo un cambio prezzo dal superadmin: l'abbonamento in corso passa al nuovo importo
     * dal prossimo rinnovo (nessun addebito o rimborso a metà periodo).
     */
    public function applyCurrentPrice(Tenant $tenant): void
    {
        if (! $tenant->hasSubscription()) {
            return;
        }

        $subscription = $this->stripe->subscriptions->retrieve($tenant->stripe_subscription_id);
        $item = $subscription->items->data[0];
        $interval = $item->price->recurring->interval;
        $priceId = $this->priceId($tenant->priceCents($interval), $interval);

        if ($item->price->id === $priceId) {
            return;
        }

        $updated = $this->stripe->subscriptions->update($subscription->id, [
            'items'              => [['id' => $item->id, 'price' => $priceId]],
            'proration_behavior' => 'none',
        ]);

        $this->syncSubscription($tenant, $updated);
    }

    /**
     * Da mensile ad annuale: parte subito, con il credito dei giorni del mese già pagati.
     * (Da annuale a mensile non si fa da qui: si perderebbe parte dell'anno pagato.)
     */
    public function switchToYearly(Tenant $tenant): void
    {
        $subscription = $this->stripe->subscriptions->retrieve($tenant->stripe_subscription_id);
        $item = $subscription->items->data[0];

        $updated = $this->stripe->subscriptions->update($subscription->id, [
            'items'              => [['id' => $item->id, 'price' => $this->priceId($tenant->priceCents('year'), 'year')]],
            'proration_behavior' => 'always_invoice',
            'payment_behavior'   => 'error_if_incomplete',
        ]);

        $this->syncSubscription($tenant, $updated);
    }

    /**
     * Fatture del cliente per la scheda superadmin (le più recenti prima).
     *
     * @return array<int, array{numero: ?string, data: string, importo_cents: int, stato: string, url: ?string, pdf: ?string}>
     */
    public function invoices(Tenant $tenant): array
    {
        if (! $tenant->stripe_customer_id) {
            return [];
        }

        return collect($this->stripe->invoices->all(['customer' => $tenant->stripe_customer_id, 'limit' => 24])->data)
            ->map(fn ($i) => [
                'numero'        => $i->number,
                'data'          => Carbon::createFromTimestamp($i->created)->toDateString(),
                'importo_cents' => (int) $i->total,
                'stato'         => (string) $i->status,
                'url'           => $i->hosted_invoice_url,
                'pdf'           => $i->invoice_pdf,
            ])->all();
    }

    /** Disdetta a fine periodo (true) o ripresa di un abbonamento disdetto (false). */
    public function cancelAtPeriodEnd(Tenant $tenant, bool $cancel): void
    {
        $this->syncSubscription($tenant, $this->stripe->subscriptions->update($tenant->stripe_subscription_id, ['cancel_at_period_end' => $cancel]));
    }

    /** Annulla subito: l'agenzia passa in sola lettura. */
    public function cancelNow(Tenant $tenant): void
    {
        $this->syncSubscription($tenant, $this->stripe->subscriptions->cancel($tenant->stripe_subscription_id));
    }

    /**
     * Garanzia "soddisfatti o rimborsati": rimborsa per intero l'ultimo pagamento riuscito e annulla subito.
     * Ritorna l'importo rimborsato in centesimi (0 se non c'era niente da rimborsare).
     */
    public function refundLastAndCancel(Tenant $tenant): int
    {
        $charge = collect($this->stripe->charges->all(['customer' => $tenant->stripe_customer_id, 'limit' => 10])->data)
            ->first(fn ($c) => $c->status === 'succeeded' && ! $c->refunded && $c->amount_refunded === 0);

        $rimborsati = 0;
        if ($charge) {
            $this->stripe->refunds->create(['charge' => $charge->id, 'metadata' => ['app' => config('billing.stripe_app'), 'tenant_id' => $tenant->id]]);
            $rimborsati = (int) $charge->amount;
        }

        if ($tenant->hasSubscription()) {
            $this->cancelNow($tenant);
        }

        Log::info('Stripe: rimborso e annullamento dal superadmin', ['tenant' => $tenant->id, 'cents' => $rimborsati]);

        return $rimborsati;
    }

    /** Portale Stripe: carta, fatture, dati di fatturazione e disdetta a fine periodo. */
    public function portalUrl(Tenant $tenant, string $returnUrl): string
    {
        return $this->stripe->billingPortal->sessions->create([
            'customer'      => $tenant->stripe_customer_id,
            'configuration' => $this->portalConfiguration(),
            'locale'        => 'it',
            'return_url'    => $returnUrl,
        ])->url;
    }

    private function priceId(int $cents, string $interval): string
    {
        $lookupKey = config('billing.stripe_app') . "_{$interval}_{$cents}";

        return Cache::rememberForever($this->cacheKey("price_{$lookupKey}"), function () use ($lookupKey, $cents, $interval) {
            $existing = $this->stripe->prices->all(['lookup_keys' => [$lookupKey], 'active' => true, 'limit' => 1])->data[0] ?? null;

            return $existing?->id ?? $this->stripe->prices->create([
                'product'      => $this->productId(),
                'currency'     => config('billing.currency'),
                'unit_amount'  => $cents,
                'tax_behavior' => 'inclusive',
                'recurring'    => ['interval' => $interval],
                'lookup_key'   => $lookupKey,
                'nickname'     => config('billing.product_name') . ' ' . number_format($cents / 100, 2, ',', '.') . ' € / ' . ($interval === 'year' ? 'anno' : 'mese'),
                'metadata'     => ['app' => config('billing.stripe_app')],
            ])->id;
        });
    }

    private function productId(): string
    {
        $app = config('billing.stripe_app');

        return Cache::rememberForever($this->cacheKey("product_{$app}"), function () use ($app) {
            foreach ($this->stripe->products->all(['active' => true, 'limit' => 100])->autoPagingIterator() as $product) {
                if (($product->metadata->app ?? null) === $app) {
                    return $product->id;
                }
            }

            return $this->stripe->products->create([
                'name'     => config('billing.product_name'),
                'metadata' => ['app' => $app],
            ])->id;
        });
    }

    private function portalConfiguration(): string
    {
        $app = config('billing.stripe_app');

        return Cache::rememberForever($this->cacheKey("portal_configuration_{$app}"), function () use ($app) {
            foreach ($this->stripe->billingPortal->configurations->all(['active' => true, 'limit' => 100])->autoPagingIterator() as $configuration) {
                if (($configuration->metadata->app ?? null) === $app) {
                    return $configuration->id;
                }
            }

            return $this->stripe->billingPortal->configurations->create([
                'metadata'         => ['app' => $app],
                'business_profile' => [
                    'headline'           => 'SinistroPro: gestisci il tuo abbonamento',
                    'privacy_policy_url' => 'https://sinistropro.it/privacy',
                ],
                'features' => [
                    'invoice_history'       => ['enabled' => true],
                    'payment_method_update' => ['enabled' => true],
                    'customer_update'       => ['enabled' => true, 'allowed_updates' => ['email', 'address', 'tax_id']],
                    'subscription_cancel'   => [
                        'enabled'             => true,
                        'mode'                => 'at_period_end',
                        'cancellation_reason' => [
                            'enabled' => true,
                            'options' => ['too_expensive', 'missing_features', 'unused', 'switched_service', 'other'],
                        ],
                    ],
                ],
            ])->id;
        });
    }

    /** Dati per la fattura dal checkout: ragione sociale, P.IVA, sede, SDI/PEC, codice fiscale. */
    public static function billingDetails(object $session): ?array
    {
        $customer = $session->customer_details ?? null;
        if (! $customer) {
            return null;
        }

        $fields = [];
        foreach ($session->custom_fields ?? [] as $field) {
            $fields[$field->key] = trim((string) ($field->text->value ?? '')) ?: null;
        }

        $address = $customer->address ?? null;

        return [
            'ragione_sociale' => $customer->name ?? null,
            'email'           => $customer->email ?? null,
            'partita_iva'     => ($customer->tax_ids ?? [])[0]->value ?? null,
            'codice_fiscale'  => $fields['codice_fiscale'] ?? null,
            'sdi_o_pec'       => $fields['sdi'] ?? null,
            'indirizzo'       => $address ? trim(($address->line1 ?? '') . ' ' . ($address->line2 ?? '')) : null,
            'cap'             => $address->postal_code ?? null,
            'citta'           => $address->city ?? null,
            'provincia'       => $address->state ?? null,
            'paese'           => $address->country ?? null,
        ];
    }
}
