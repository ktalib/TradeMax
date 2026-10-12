<?php

declare(strict_types=1);

namespace App\Domain\Assets\Actions;

use App\Domain\Assets\Events\AssetWithdrawalsHalted;
use App\Domain\Assets\Exceptions\UnknownAsset;
use App\Domain\Assets\Models\Asset;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stops withdrawals of one asset (reconciliation mismatch, incident, staff decision).
 * If it's already halted the first reason is kept, so the original cause isn't lost.
 */
final class HaltAssetWithdrawals
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return bool whether this call halted it (false if it already was)
     */
    public function handle(int $assetId, string $reason, ?Authenticatable $actor = null): bool
    {
        $asset = Asset::query()->find($assetId) ?? throw UnknownAsset::id($assetId);

        if ($asset->withdrawals_halted_at !== null) {
            return false;
        }

        $asset->forceFill([
            'withdrawals_halted_at' => CarbonImmutable::now(),
            'withdrawals_halt_reason' => mb_substr($reason, 0, 255),
        ])->save();

        $this->audit->record('asset.withdrawals_halted', $asset, null, ['reason' => $reason], $actor);

        AssetWithdrawalsHalted::dispatch($asset->id, $asset->symbol, $reason);

        return true;
    }
}
