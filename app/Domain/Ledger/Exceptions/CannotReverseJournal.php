<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use RuntimeException;

final class CannotReverseJournal extends RuntimeException
{
    public static function alreadyReversed(string $publicId): self
    {
        return new self("Journal {$publicId} has already been reversed.");
    }

    public static function isAReversal(string $publicId): self
    {
        return new self("Journal {$publicId} is itself a reversal; post a new journal instead.");
    }
}
