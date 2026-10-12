<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

final readonly class InvariantViolation
{
    public const UNBALANCED_JOURNAL = 'unbalanced_journal';

    public const UNBALANCED_ASSET = 'unbalanced_asset';

    public const NEGATIVE_BALANCE = 'negative_balance';

    public const BALANCE_DRIFT = 'balance_drift';

    public const ASSET_MISMATCH = 'asset_mismatch';

    public function __construct(
        public string $rule,
        public string $message,
    ) {}
}
