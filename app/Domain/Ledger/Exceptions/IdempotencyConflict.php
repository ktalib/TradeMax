<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use RuntimeException;

/**
 * The idempotency key was already used for a journal with different entries. Returning
 * the old journal would hide a bug, so this fails loudly instead.
 */
final class IdempotencyConflict extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Idempotency key [{$key}] was already posted with different entries.");
    }
}
