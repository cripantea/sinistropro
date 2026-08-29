<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'),
    ],

    'whatsapp_cloud' => [
        // Fallback: usato solo dai tenant collegati manualmente col comando
        // whatsapp:link-number (token condiviso). I tenant in coexistence usano
        // il proprio whatsapp_sessions.access_token, per-WABA.
        'token'        => env('WHATSAPP_CLOUD_ACCESS_TOKEN'),
        'api_version'  => env('WHATSAPP_CLOUD_API_VERSION', 'v21.0'),
        'app_secret'   => env('WHATSAPP_CLOUD_APP_SECRET'),
        'verify_token' => env('WHATSAPP_CLOUD_VERIFY_TOKEN'),
        // Logga il body grezzo dei webhook history/smb_app_state_sync/smb_message_echoes:
        // utile solo in fase di validazione della coexistence contro un ambiente Meta reale.
        'log_raw_webhooks' => env('WHATSAPP_CLOUD_LOG_RAW_WEBHOOKS', false),
    ],

    // App Meta per l'Embedded Signup (coesistenza WhatsApp Business App + Cloud API).
    // Credenziali a livello di app, uniche per tutta la piattaforma.
    'facebook' => [
        'app_id'                    => env('FACEBOOK_APP_ID'),
        'app_secret'                => env('FACEBOOK_APP_SECRET'),
        'graph_version'             => env('FACEBOOK_GRAPH_VERSION', 'v25.0'),
        'embedded_signup_config_id' => env('FACEBOOK_EMBEDDED_SIGNUP_CONFIG_ID'),
    ],

    // FusionWA: plugin di connettività WhatsApp (Coexistence + invio messaggi).
    // Il token WABA per-cliente è custodito da FusionWA, mai esposto qui.
    'fusionwa' => [
        'base_url'   => env('FUSIONWA_BASE_URL', 'https://wa.fusionsoft.it'),
        'api_key'    => env('FUSIONWA_API_KEY'),
        'api_secret' => env('FUSIONWA_API_SECRET'),
    ],

];
