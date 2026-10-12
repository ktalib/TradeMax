<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use App\Domain\Ledger\Enums\BalanceType;
use App\Support\Money\Decimal;
use RuntimeException;

/**
 * Posting would take a wallet that can't go negative below zero. Nothing was written.
 */
final class InsufficientBalance extends RuntimeException
{
    public function __construct(
        public readonly int $walletId,
        public readonly BalanceType $balanceType,
        public readonly Decimal $balance,
        public readonly Decimal $required,
    ) {
        parent::__construct("Wallet {$walletId} has {$balance} {$balanceType->value}, needs {$required}.");
    }
}
