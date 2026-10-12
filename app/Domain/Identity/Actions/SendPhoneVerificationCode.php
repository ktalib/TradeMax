<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\OneTimeCodeNotification;
use App\Domain\Identity\Services\OtpService;

final class SendPhoneVerificationCode
{
    public function __construct(private readonly OtpService $otp) {}

    public function handle(User $user): void
    {
        $code = $this->otp->issue($user, OtpPurpose::PhoneVerification, OtpChannel::Sms, $user->phone);

        $user->notify(new OneTimeCodeNotification($code, OtpPurpose::PhoneVerification, OtpChannel::Sms));
    }
}
