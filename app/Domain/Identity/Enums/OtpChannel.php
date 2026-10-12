<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum OtpChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
}
