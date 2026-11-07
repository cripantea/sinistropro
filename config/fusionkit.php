<?php

// Fusion Kit — sorgente unica in fusion-id/kit/laravel (copiata da kit/sync.sh: non modificare qui).
// Le variabili le genera fusion-id/scripts/setup.mjs (out/<target>/env/<prodotto>.env).

return [
    'issuer' => rtrim((string) env('FUSION_ID_ISSUER', ''), '/'),
    'client_id' => env('FUSION_ID_CLIENT_ID'),
    'client_secret' => env('FUSION_ID_CLIENT_SECRET'),
    'google_idp_id' => env('FUSION_ID_GOOGLE_IDP_ID'),
    'app_url' => rtrim((string) env('FUSION_ID_APP_URL', env('APP_URL', '')), '/'),
    // on = resta anche il vecchio login con password (transizione); off = solo Fusion ID.
    'legacy_login' => strtolower((string) env('FUSION_ID_LEGACY_LOGIN', 'on')) !== 'off',
    'product' => env('FUSION_KIT_PRODUCT'),
    'kit_secret' => env('FUSION_KIT_SECRET'),
    'control_url' => rtrim((string) env('FUSION_CONTROL_URL', ''), '/'),
];
