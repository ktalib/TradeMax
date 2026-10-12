<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Ledger\Enums\CustodySource;
use App\Domain\Ledger\Models\CustodyBalance;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * Records what custody actually holds, for ledger:reconcile to compare against.
 */
final class RecordCustodyBalance
{
    public function __construct(
        private readonly AssetCatalog $assets,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(
        int $assetId,
        Decimal $balance,
        CarbonImmutable $asOf,
        CustodySource $source,
        ?int $adminId = null,
        ?string $note = null,
        ?Authenticatable $actor = null,
    ): CustodyBalance {
        $asset = $this->assets->find($assetId);

        if ($balance->isNegative()) {
            throw new InvalidArgumentException('Custody balances cannot be negative.');
        }

        if ($asOf->isFuture()) {
            throw new InvalidArgumentException('Custody balances cannot be dated in the future.');
        }

        if ($source === CustodySource::Manual && $adminId === null) {
            throw new InvalidArgumentException('A manual custody balance must name the staff member who entered it.');
        }

        $record = CustodyBalance::query()->create([
            'asset_id' => $asset->id,
            'source' => $source,
            'balance' => $balance->toAssetPrecision($asset->precision),
            'as_of' => $asOf,
            'recorded_by_admin_id' => $adminId,
            'note' => $note,
        ]);

        $this->audit->record('ledger.custody_recorded', $record, null, [
            'asset' => $asset->symbol,
            'balance' => $record->balance->toString(),
            'as_of' => $asOf->toIso8601String(),
            'source' => $source->value,
        ], $actor);

        return $record;
    }
}
