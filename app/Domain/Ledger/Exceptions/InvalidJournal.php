<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use LogicException;

/**
 * A programming error in the caller: the journal can never be posted as built.
 */
final class InvalidJournal extends LogicException
{
    public static function tooFewEntries(): self
    {
        return new self('A journal needs at least two entries.');
    }

    public static function nonPositiveAmount(int $walletId): self
    {
        return new self("Entry for wallet {$walletId} must have an amount greater than zero.");
    }

    public static function tooPrecise(int $walletId, string $amount, int $precision): self
    {
        return new self("Entry for wallet {$walletId} ({$amount}) has more than {$precision} decimals.");
    }

    public static function unbalanced(int $assetId, string $debits, string $credits): self
    {
        return new self("Asset {$assetId} is unbalanced: debits {$debits}, credits {$credits}.");
    }

    public static function unknownWallet(int $walletId): self
    {
        return new self("Wallet {$walletId} does not exist.");
    }

    public static function assetMismatch(int $walletId, int $entryAsset, int $walletAsset): self
    {
        return new self("Entry says asset {$entryAsset} but wallet {$walletId} holds asset {$walletAsset}.");
    }

    public static function emptyKey(): self
    {
        return new self('A journal needs an idempotency key.');
    }
}
