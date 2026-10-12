<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\OneTimeCodeNotification;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Step-up authentication for sensitive actions: a fresh authenticator code AND a code
 * emailed for this purpose. Both are checked on every sensitive action; nothing is cached.
 */
final class StepUpVerifier
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $totp,
        private readonly OtpService $otp,
    ) {}

    public function sendEmailCode(User $user): void
    {
        $code = $this->otp->issue($user, OtpPurpose::StepUp, OtpChannel::Email, $user->email);

        $user->notify(new OneTimeCodeNotification($code, OtpPurpose::StepUp, OtpChannel::Email));
    }

    /**
     * @throws ValidationException with errors keyed `totp_code` / `email_code`
     */
    public function verify(User $user, ?string $totpCode, ?string $emailCode): void
    {
        if (! $this->verifyTotp($user, $totpCode)) {
            throw ValidationException::withMessages(['totp_code' => __('That authenticator code is not valid. Enter the current 6-digit code from your app.')]);
        }

        if ($emailCode === null || ! $this->otp->verify($user, OtpPurpose::StepUp, $emailCode, $user->email)) {
            throw ValidationException::withMessages(['email_code' => __('That email code is wrong or has expired. Request a new code and try again.')]);
        }
    }

    public function verifyTotp(User $user, ?string $code): bool
    {
        if ($code === null || ! $user->hasConfirmedTwoFactor() || $user->two_factor_secret === null) {
            return false;
        }

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return is_string($secret) && $this->totp->verify($secret, preg_replace('/\D/', '', $code) ?? '');
    }
}
