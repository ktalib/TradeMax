<?php

declare(strict_types=1);

return [

    // log (local: codes go to storage/logs) | fake (tests) | failover (Phase 6)
    'driver' => env('SMS_DRIVER', 'log'),

    'primary' => env('SMS_PRIMARY', 'twilio'),

    'fallback' => env('SMS_FALLBACK', 'vonage'),

    // After this many failed SMS attempts an OTP falls back to voice, then email.
    'max_sms_attempts' => 2,

    'providers' => [
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'verify_sid' => env('TWILIO_VERIFY_SID'),
        ],
        'vonage' => [
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
        ],
    ],

];
