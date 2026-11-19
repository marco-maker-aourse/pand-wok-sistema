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

    // Integraciones opcionales: las credenciales viven exclusivamente en .env.
    // Los proveedores del asistente de IA se configuran en config/asistente.php.

    // API Perú o proveedor equivalente de DNI/RUC. Se habilita al definir URL y token.
    'api_peru' => [
        'dni_url' => env('API_PERU_DNI_URL'),
        'ruc_url' => env('API_PERU_RUC_URL'),
        'token'   => env('API_PERU_TOKEN'),
        // `bearer` para ApiPeruDev; `query` para dniruc.apisperu.com.
        'auth_mode' => env('API_PERU_AUTH_MODE', 'bearer'),
    ],

];
