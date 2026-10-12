<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Security-relevant account events. Each one is audited and sent to the user;
 * the ones that weaken account protection also lock withdrawals for 24 hours.
 */
enum SecurityEvent: string
{
    case NewDeviceLogin = 'new_device_login';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case EmailChanged = 'email_changed';
    case PhoneChanged = 'phone_changed';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
    case AccountFrozen = 'account_frozen';
    case ApiKeyCreated = 'api_key_created';
    case ApiKeyRevoked = 'api_key_revoked';
    case AntiPhishingCodeChanged = 'anti_phishing_code_changed';

    public function locksWithdrawals(): bool
    {
        return in_array($this, [
            self::PasswordChanged,
            self::PasswordReset,
            self::EmailChanged,
            self::PhoneChanged,
            self::TwoFactorDisabled,
        ], true);
    }

    /** Mandatory SMS per the notification matrix (master prompt §8.11). */
    public function sendsSms(): bool
    {
        return in_array($this, [
            self::PasswordChanged,
            self::PasswordReset,
            self::EmailChanged,
            self::PhoneChanged,
            self::TwoFactorEnabled,
            self::TwoFactorDisabled,
            self::AccountFrozen,
        ], true);
    }

    public function subject(): string
    {
        return match ($this) {
            self::NewDeviceLogin => __('New sign-in to your account'),
            self::PasswordChanged => __('Your password was changed'),
            self::PasswordReset => __('Your password was reset'),
            self::EmailChanged => __('Your email address was changed'),
            self::PhoneChanged => __('Your phone number was changed'),
            self::TwoFactorEnabled => __('Two-step verification is on'),
            self::TwoFactorDisabled => __('Two-step verification was turned off'),
            self::RecoveryCodesRegenerated => __('New recovery codes were created'),
            self::AccountFrozen => __('Your account is frozen'),
            self::ApiKeyCreated => __('A new API key was created'),
            self::ApiKeyRevoked => __('An API key was revoked'),
            self::AntiPhishingCodeChanged => __('Your anti-phishing code was changed'),
        };
    }
}
