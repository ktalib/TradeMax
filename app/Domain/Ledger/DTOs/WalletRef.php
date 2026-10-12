<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\WalletType;

/**
 * A handle on a wallet for other modules; they never load the Wallet model.
 */
final readonly class WalletRef
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public int $assetId,
        public WalletType $type,
    ) {}
}
