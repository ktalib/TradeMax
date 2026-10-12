<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Critical: custody and the ledger disagree beyond tolerance for an asset.
 */
final class ReconciliationMismatchDetected
{
    use Dispatchable;

    public function __construct(
        public readonly int $reportId,
        public readonly int $assetId,
        public readonly string $symbol,
        public readonly string $difference,
    ) {}
}
