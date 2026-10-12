<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Support\Money\Decimal;

/**
 * One line of a journal. The amount is always positive; the direction says which way.
 */
final readonly class EntryDTO
{
    public function __construct(
        public int $walletId,
        public int $assetId,
        public EntryDirection $direction,
        public Decimal $amount,
        public BalanceType $balanceType = BalanceType::Available,
    ) {}

    public static function debit(WalletRef $wallet, Decimal|string $amount, BalanceType $balanceType = BalanceType::Available): self
    {
        return new self($wallet->id, $wallet->assetId, EntryDirection::Debit, Decimal::of($amount), $balanceType);
    }

    public static function credit(WalletRef $wallet, Decimal|string $amount, BalanceType $balanceType = BalanceType::Available): self
    {
        return new self($wallet->id, $wallet->assetId, EntryDirection::Credit, Decimal::of($amount), $balanceType);
    }

    /**
     * The same line in the other direction, for reversals.
     */
    public function mirrored(): self
    {
        return new self($this->walletId, $this->assetId, $this->direction->opposite(), $this->amount, $this->balanceType);
    }

    /**
     * Signed effect on the wallet: positive for a credit.
     */
    public function signedAmount(): Decimal
    {
        return $this->direction === EntryDirection::Credit ? $this->amount : $this->amount->negate();
    }
}
