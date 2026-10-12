<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum PostedByType: string
{
    case System = 'system';
    case User = 'user';
    case Admin = 'admin';
}
