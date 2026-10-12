<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\OneTimeCode;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Six-digit one-time codes for email and SMS. Only an HMAC of the code is stored; a code
 * works once, for 10 minutes, for at most 5 guesses, and only for the purpose (and
 * destination) it was issued for. Issuing a new code retires the previous one.
 */
final class OtpService
{
    private const MAX_ISSUES = 3;

    private const ISSUE_WINDOW_SECONDS = 600;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly string $hmacKey,
    ) {}

    /**
     * @return string the plain code, to be delivered by the caller and never stored
     */
    public function issue(User $user, OtpPurpose $purpose, OtpChannel $channel, string $destination): string
    {
        $rateKey = sprintf('otp-issue:%d:%s', $user->id, $purpose->value);

        if ($this->limiter->tooManyAttempts($rateKey, self::MAX_ISSUES)) {
            throw ValidationException::withMessages([
                'code' => __('Too many codes requested. Try again in :seconds seconds.', ['seconds' => $this->limiter->availableIn($rateKey)]),
            ]);
        }

        $this->limiter->hit($rateKey, self::ISSUE_WINDOW_SECONDS);

        $now = CarbonImmutable::now();

        OneTimeCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->update(['expires_at' => $now]);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        OneTimeCode::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'destination_hash' => $this->destinationHash($destination),
            'code_hash' => $this->codeHash($user, $purpose, $code),
            'expires_at' => $now->addMinutes($purpose->ttlMinutes()),
        ]);

        return $code;
    }

    /**
     * True only for the current, unexpired, unused code. Each call counts as a guess.
     */
    public function verify(User $user, OtpPurpose $purpose, string $code, ?string $destination = null): bool
    {
        $otp = OneTimeCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', CarbonImmutable::now())
            ->latest('id')
            ->first();

        if ($otp === null || ! $otp->isUsable()) {
            return false;
        }

        $otp->increment('attempts');

        if ($destination !== null && ! hash_equals($otp->destination_hash, $this->destinationHash($destination))) {
            return false;
        }

        $normalised = preg_replace('/\D/', '', $code) ?? '';

        if (! hash_equals($otp->code_hash, $this->codeHash($user, $purpose, $normalised))) {
            return false;
        }

        // Conditional update: if two requests race with the right code, only one consumes it.
        return OneTimeCode::query()
            ->whereKey($otp->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => CarbonImmutable::now()]) === 1;
    }

    private function codeHash(User $user, OtpPurpose $purpose, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$purpose->value.'|'.$code, $this->hmacKey);
    }

    private function destinationHash(string $destination): string
    {
        return hash('sha256', mb_strtolower(trim($destination)));
    }
}
