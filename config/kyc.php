<?php

declare(strict_types=1);

return [

    'driver' => env('KYC_DRIVER', 'fake'),

    'retention_years' => (int) env('KYC_RETENTION_YEARS', 5),

    'expiry_reminder_days' => 30,

    'providers' => [
        'sumsub' => [
            'app_token' => env('SUMSUB_APP_TOKEN'),
            'secret' => env('SUMSUB_SECRET'),
            'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET'),
        ],
        'onfido' => [
            'api_token' => env('ONFIDO_API_TOKEN'),
            'webhook_token' => env('ONFIDO_WEBHOOK_TOKEN'),
        ],
        'veriff' => [
            'api_key' => env('VERIFF_API_KEY'),
            'shared_secret' => env('VERIFF_SHARED_SECRET'),
        ],
    ],

];
