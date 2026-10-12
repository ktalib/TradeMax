<?php

declare(strict_types=1);

return [

    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    'payout_driver' => env('PAYOUT_DRIVER', 'manual'),

    'custody_driver' => env('CUSTODY_DRIVER', 'manual'),

    'onramp_driver' => env('ONRAMP_DRIVER', 'null'),

    'chain_analytics_driver' => env('CHAIN_ANALYTICS_DRIVER', 'null'),

    'travel_rule_driver' => env('TRAVEL_RULE_DRIVER', 'null'),

    'gateways' => [
        'nowpayments' => [
            'api_key' => env('NOWPAYMENTS_API_KEY'),
            'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
            'sandbox' => (bool) env('NOWPAYMENTS_SANDBOX', true),
        ],
        'coingate' => [
            'api_key' => env('COINGATE_API_KEY'),
            'sandbox' => (bool) env('COINGATE_SANDBOX', true),
        ],
    ],

    'chain_verifiers' => [
        'etherscan_api_key' => env('ETHERSCAN_API_KEY'),
        'bscscan_api_key' => env('BSCSCAN_API_KEY'),
        'trongrid_api_key' => env('TRONGRID_API_KEY'),
        'blockstream_base_url' => env('BLOCKSTREAM_BASE_URL', 'https://blockstream.info/api'),
    ],

    'manual_deposits' => [
        // Retry schedule (minutes) for unconfirmed manual-deposit transactions, then hourly to 24h.
        'retry_minutes' => [1, 3, 5, 10, 20, 30, 60],
        'give_up_after_hours' => 24,
        'not_found_reject_after_hours' => 2,
    ],

    'uploads' => [
        'max_kilobytes' => 10240,
        'virus_scan_driver' => env('VIRUS_SCAN_DRIVER', 'null'),
    ],

];
