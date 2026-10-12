<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Repositories\Contracts\Wallets;

/**
 * Creates the system counter-accounts for every asset (or one). Idempotent.
 * The demo faucet only exists when APP_DEMO is on.
 */
final class EnsureSystemWallets
{
    public function __construct(
        private readonly AssetCatalog $assets,
        private readonly Wallets $wallets,
    ) {}

    /**
     * @return int number of asset/type pairs ensured
     */
    public function handle(?int $assetId = null): int
    {
        $assets = $assetId === null ? $this->assets->all() : [$this->assets->find($assetId)];
        $types = WalletType::systemTypes(includeDemo: (bool) config('platform.demo'));
        $count = 0;

        foreach ($assets as $asset) {
            foreach ($types as $type) {
                $this->wallets->system($asset->id, $type);
                $count++;
            }
        }

        return $count;
    }
}
