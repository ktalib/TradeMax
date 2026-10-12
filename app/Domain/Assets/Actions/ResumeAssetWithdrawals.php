<?php

declare(strict_types=1);

namespace App\Domain\Assets\Actions;

use App\Domain\Assets\Exceptions\UnknownAsset;
use App\Domain\Assets\Models\Asset;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Staff lift a halt once the cause is resolved. Always attributed to a person.
 */
final class ResumeAssetWithdrawals
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(int $assetId, Authenticatable $actor, string $note): void
    {
        $asset = Asset::query()->find($assetId) ?? throw UnknownAsset::id($assetId);

        if ($asset->withdrawals_halted_at === null) {
            return;
        }

        $before = ['reason' => $asset->withdrawals_halt_reason];

        $asset->forceFill(['withdrawals_halted_at' => null, 'withdrawals_halt_reason' => null])->save();

        $this->audit->record('asset.withdrawals_resumed', $asset, $before, ['note' => $note], $actor);
    }
}
