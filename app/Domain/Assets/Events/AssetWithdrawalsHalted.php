<?php

declare(strict_types=1);

namespace App\Domain\Assets\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class AssetWithdrawalsHalted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $assetId,
        public readonly string $symbol,
        public readonly string $reason,
    ) {}
}
