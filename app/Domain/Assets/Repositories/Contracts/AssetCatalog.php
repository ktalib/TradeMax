<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repositories\Contracts;

use App\Domain\Assets\DTOs\AssetData;
use App\Domain\Assets\Exceptions\UnknownAsset;

/**
 * Read access to assets for other modules.
 */
interface AssetCatalog
{
    /**
     * @throws UnknownAsset
     */
    public function find(int $id): AssetData;

    /**
     * @throws UnknownAsset
     */
    public function findBySymbol(string $symbol): AssetData;

    /**
     * @return list<AssetData>
     */
    public function all(): array;
}
