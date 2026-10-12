<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Assets\Actions\HaltAssetWithdrawals;
use App\Domain\Assets\DTOs\AssetData;
use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Ledger\Enums\ReconciliationStatus;
use App\Domain\Ledger\Events\ReconciliationMismatchDetected;
use App\Domain\Ledger\Models\CustodyBalance;
use App\Domain\Ledger\Models\ReconciliationReport;
use App\Domain\Ledger\Repositories\Contracts\LedgerTotals;
use Psr\Log\LoggerInterface;

/**
 * Compares the latest custody figure per asset with what the ledger says custody should
 * hold at that same moment, -(system_hot + system_cold). A difference beyond the asset's
 * tolerance halts that asset's withdrawals and raises a critical alert.
 *
 * An asset with no custody figure yet is reported as `missing` and not halted: there is
 * nothing to compare (RUNBOOK: finance records custody before go-live).
 */
final class ReconcileLedger
{
    public function __construct(
        private readonly AssetCatalog $assets,
        private readonly LedgerTotals $totals,
        private readonly HaltAssetWithdrawals $halt,
        private readonly LoggerInterface $log,
    ) {}

    /**
     * @return list<ReconciliationReport>
     */
    public function handle(?int $assetId = null): array
    {
        $assets = $assetId === null ? $this->assets->all() : [$this->assets->find($assetId)];

        return array_map(fn (AssetData $asset): ReconciliationReport => $this->reconcile($asset), $assets);
    }

    private function reconcile(AssetData $asset): ReconciliationReport
    {
        $custody = CustodyBalance::query()
            ->where('asset_id', $asset->id)
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->first();

        if ($custody === null) {
            return ReconciliationReport::query()->create([
                'asset_id' => $asset->id,
                'status' => ReconciliationStatus::Missing,
                'ledger_custody' => $this->totals->custodyLiability($asset->id),
                'tolerance' => $asset->reconcileTolerance,
                'withdrawals_halted' => false,
            ]);
        }

        $ledger = $this->totals->custodyLiability($asset->id, $custody->as_of);
        $difference = $custody->balance->sub($ledger);
        $mismatch = $difference->abs()->gt($asset->reconcileTolerance);

        $report = ReconciliationReport::query()->create([
            'asset_id' => $asset->id,
            'status' => $mismatch ? ReconciliationStatus::Mismatch : ReconciliationStatus::Matched,
            'ledger_custody' => $ledger,
            'reported_custody' => $custody->balance,
            'difference' => $difference,
            'tolerance' => $asset->reconcileTolerance,
            'custody_balance_id' => $custody->id,
            'withdrawals_halted' => $mismatch,
        ]);

        if ($mismatch) {
            $this->halt->handle($asset->id, "Reconciliation mismatch {$difference} {$asset->symbol} (report {$report->public_id})");

            $this->log->critical('Ledger reconciliation mismatch', [
                'asset' => $asset->symbol,
                'ledger' => $ledger->toString(),
                'custody' => $custody->balance->toString(),
                'difference' => $difference->toString(),
            ]);

            ReconciliationMismatchDetected::dispatch($report->id, $asset->id, $asset->symbol, $difference->toString());
        }

        return $report;
    }
}
