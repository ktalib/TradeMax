<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum OtpPurpose: string
{
    case PhoneVerification = 'phone_verification';
    case NewDevice = 'new_device';
    case StepUp = 'step_up';
    case PhoneChange = 'phone_change';

    public function ttlMinutes(): int
    {
        return 10;
    }
}
