<?php

declare(strict_types=1);

namespace App\Domain\Assets\DTOs;

use App\Domain\Assets\Models\Asset;
use App\Support\Money\Decimal;

/**
 * What other modules may know about an asset. They never load the Asset model.
 */
final readonly class AssetData
{
    public function __construct(
        public int $id,
        public string $symbol,
        public string $name,
        public int $precision,
        public int $displayPrecision,
        public bool $isActive,
        public Decimal $reconcileTolerance,
        public bool $withdrawalsHalted,
    ) {}

    public static function fromModel(Asset $asset): self
    {
        return new self(
            id: $asset->id,
            symbol: $asset->symbol,
            name: $asset->name,
            precision: $asset->precision,
            displayPrecision: $asset->display_precision,
            isActive: $asset->is_active,
            reconcileTolerance: $asset->reconcile_tolerance,
            withdrawalsHalted: $asset->withdrawals_halted_at !== null,
        );
    }
}
