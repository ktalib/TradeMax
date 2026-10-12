<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Support\Money\Decimal;

final readonly class WalletBalance
{
    public function __construct(
        public Decimal $available,
        public Decimal $locked,
    ) {}

    public function total(): Decimal
    {
        return $this->available->add($this->locked);
    }
}
