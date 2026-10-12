<?php

declare(strict_types=1);

/*
| Platform-wide defaults. Admin-editable values are overridden by the `settings` table
| (Phase 10). Every money value is a numeric string and must be read via Decimal::of().
*/

return [

    // Deposits stay disabled and a banner is shown until the operator confirms licensing (RUNBOOK).
    'live_mode' => (bool) env('PLATFORM_LIVE_MODE', false),

    // Demo seeders and the "DEMO ENVIRONMENT" banner. Never true in production.
    'demo' => (bool) env('APP_DEMO', false),

    'admin_timezone' => env('ADMIN_TIMEZONE', 'UTC'),

    'locales' => explode(',', (string) env('SUPPORTED_LOCALES', 'en,fr,es,pt,ar,zh,ru,tr')),
    'rtl_locales' => ['ar', 'he', 'fa', 'ur'],

    'default_display_fiat' => env('DEFAULT_DISPLAY_FIAT', 'USD'),

    /*
    | Starting status for the country seeder only: comprehensively sanctioned jurisdictions.
    | After seeding, country statuses are managed in the admin panel.
    */
    'blocked_countries_default' => ['CU', 'IR', 'KP', 'SY'],

    /*
    | KYC tiers (ADR-0001). Limits are USD equivalents in rolling windows.
    */
    'kyc_tiers' => [
        0 => ['daily_withdraw_usd' => '0', 'monthly_withdraw_usd' => '0', 'daily_deposit_usd' => '0', 'can_trade' => false, 'can_stake' => false, 'can_copy_trade' => false, 'can_lead_trade' => false, 'can_api_trade' => false],
        1 => ['daily_withdraw_usd' => '1000', 'monthly_withdraw_usd' => '10000', 'daily_deposit_usd' => '10000', 'can_trade' => true, 'can_stake' => true, 'can_copy_trade' => false, 'can_lead_trade' => false, 'can_api_trade' => false],
        2 => ['daily_withdraw_usd' => '10000', 'monthly_withdraw_usd' => '100000', 'daily_deposit_usd' => '100000', 'can_trade' => true, 'can_stake' => true, 'can_copy_trade' => true, 'can_lead_trade' => false, 'can_api_trade' => false],
        3 => ['daily_withdraw_usd' => '50000', 'monthly_withdraw_usd' => '500000', 'daily_deposit_usd' => '500000', 'can_trade' => true, 'can_stake' => true, 'can_copy_trade' => true, 'can_lead_trade' => true, 'can_api_trade' => true],
    ],

    'withdrawals' => [
        'auto_approve_limit_usd' => env('WITHDRAW_AUTO_APPROVE_LIMIT_USD', '1000'),
        'dual_approval_limit_usd' => env('WITHDRAW_DUAL_APPROVAL_LIMIT_USD', '25000'),
        'new_address_cooldown_hours' => 24,
        'security_change_lock_hours' => 24,
        'recovery_lock_hours' => 48,
        'allow_one_off_addresses_for_tier' => 3,
    ],

    'security' => [
        'idle_logout_minutes' => (int) env('IDLE_LOGOUT_MINUTES', 30),
        'admin_idle_logout_minutes' => 15,
        'trusted_device_days' => 30,
        'password_min_length' => 10,
        'password_min_zxcvbn_score' => 3,
        'check_breached_passwords' => (bool) env('CHECK_BREACHED_PASSWORDS', true),
        // CAPTCHA appears on the login form after this many failures from one IP in 15 minutes.
        'captcha_after_failed_logins' => 3,
        'max_api_keys' => 10,
        // WebAuthn passkeys (Fortify) stay off until the passkey UI ships.
        'passkeys_enabled' => (bool) env('PASSKEYS_ENABLED', false),
    ],

    /*
    | Current versions of the legal documents users accept at registration. Bump a version
    | when a document changes materially; users are asked to accept it again (Phase 10).
    */
    'legal' => [
        'terms' => '2026-10-01',
        'privacy' => '2026-10-01',
        'risk_disclosure' => '2026-10-01',
    ],

    'travel_rule' => [
        'threshold_usd' => env('TRAVEL_RULE_THRESHOLD_USD', '1000'),
    ],

    'ledger' => [
        // Absolute USD difference tolerated by ledger:reconcile before withdrawals are halted.
        'reconcile_tolerance_usd' => env('LEDGER_RECONCILE_TOLERANCE_USD', '1'),
    ],

];
