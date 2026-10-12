<?php

declare(strict_types=1);

namespace App\Support\Money\Exceptions;

use DomainException;

final class DivisionByZero extends DomainException
{
    public static function create(): self
    {
        return new self('Cannot divide a Decimal by zero.');
    }
}
