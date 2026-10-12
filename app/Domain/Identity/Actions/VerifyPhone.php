<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\OtpService;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class VerifyPhone
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, string $code): void
    {
        if (! $this->otp->verify($user, OtpPurpose::PhoneVerification, $code, $user->phone)) {
            throw ValidationException::withMessages(['code' => __('That code is wrong or has expired. Request a new code and try again.')]);
        }

        $user->forceFill(['phone_verified_at' => CarbonImmutable::now()])->save();

        $this->audit->record('user.phone_verified', $user);
    }
}
