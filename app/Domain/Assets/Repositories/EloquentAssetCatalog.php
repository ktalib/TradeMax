<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repositories;

use App\Domain\Assets\DTOs\AssetData;
use App\Domain\Assets\Exceptions\UnknownAsset;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Repositories\Contracts\AssetCatalog;

/**
 * Reads straight from the database: precision and halt flags must never be stale on a
 * money path. Callers that need many lookups hold on to the DTOs.
 */
final class EloquentAssetCatalog implements AssetCatalog
{
    public function find(int $id): AssetData
    {
        $asset = Asset::query()->find($id);

        return $asset !== null ? AssetData::fromModel($asset) : throw UnknownAsset::id($id);
    }

    public function findBySymbol(string $symbol): AssetData
    {
        $asset = Asset::query()->where('symbol', strtoupper($symbol))->first();

        return $asset !== null ? AssetData::fromModel($asset) : throw UnknownAsset::symbol($symbol);
    }

    public function all(): array
    {
        return array_values(Asset::query()->orderBy('sort')->orderBy('id')->get()
            ->map(static fn (Asset $asset): AssetData => AssetData::fromModel($asset))
            ->all());
    }
}
