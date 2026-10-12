<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories\Contracts;

use App\Domain\Ledger\DTOs\WalletBalance;
use App\Domain\Ledger\DTOs\WalletRef;
use App\Domain\Ledger\Enums\WalletType;

/**
 * How other modules find wallets to post against. Wallets are created on first use.
 */
interface Wallets
{
    public function forUser(int $userId, int $assetId, WalletType $type = WalletType::Spot): WalletRef;

    public function system(int $assetId, WalletType $type): WalletRef;

    public function balance(int $walletId): WalletBalance;

    /**
     * @return array<int, WalletBalance> keyed by asset id; assets without a wallet are absent
     */
    public function balancesForUser(int $userId, WalletType $type = WalletType::Spot): array;
}
