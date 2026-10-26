<?php

// Abbonamento SinistroPro con Stripe. Importi in centesimi, IVA inclusa.
// Ogni tenant può avere prezzi propri (tenants.price_*_cents): questi sono i valori standard.
return [
    'price_monthly_cents' => (int) env('BILLING_PRICE_MONTHLY_CENTS', 9900),
    'price_yearly_cents'  => (int) env('BILLING_PRICE_YEARLY_CENTS', 99000),
    'currency'            => 'eur',

    // L'account Stripe è condiviso con gli altri prodotti Fusion: tutto ciò che creiamo
    // (prodotto, prezzi, checkout, abbonamenti, portale) porta metadata app = questo valore.
    'stripe_app'          => 'sinistropro',
    'product_name'        => 'SinistroPro',
];
