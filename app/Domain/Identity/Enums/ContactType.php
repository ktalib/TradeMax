<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum ContactType: string
{
    case Email = 'email';
    case Phone = 'phone';
}
