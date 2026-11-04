<?php

// Abbonamento SinistroPro con Stripe. Importi in centesimi, IVA inclusa.
// Ogni tenant può avere prezzi propri (tenants.price_*_cents): questi sono i valori standard.
return [
    'price_monthly_cents' => (int) env('BILLING_PRICE_MONTHLY_CENTS', 9900),
    'price_yearly_cents'  => (int) env('BILLING_PRICE_YEARLY_CENTS', 99000),
    'currency'            => 'eur',

    // Prova gratuita per chi si registra da solo (/registrati): carta subito, primo addebito
    // alla fine della prova. Dopo la demo (link /paga) si paga subito. 0 = niente prova.
    'trial_days'          => (int) env('BILLING_TRIAL_DAYS', 30),

    // L'account Stripe è condiviso con gli altri prodotti Fusion: tutto ciò che creiamo
    // (prodotto, prezzi, checkout, abbonamenti, portale) porta metadata app = questo valore.
    'stripe_app'          => 'sinistropro',
    'product_name'        => 'SinistroPro',
];
