<?php

declare(strict_types=1);

namespace App\Support\Money\Exceptions;

use InvalidArgumentException;

final class InvalidDecimal extends InvalidArgumentException
{
    public static function fromInput(string $input): self
    {
        $shown = mb_strlen($input) > 40 ? mb_substr($input, 0, 40).'…' : $input;

        return new self(sprintf('"%s" is not a valid decimal number. Use digits with an optional sign and decimal point.', $shown));
    }
}
