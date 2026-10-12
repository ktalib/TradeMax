<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Console;

use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Ledger\Actions\ReconcileLedger;
use App\Domain\Ledger\Enums\ReconciliationStatus;
use App\Domain\Ledger\Models\ReconciliationReport;
use Illuminate\Console\Command;

final class ReconcileCommand extends Command
{
    protected $signature = 'ledger:reconcile {asset? : asset symbol; all assets when omitted}';

    protected $description = 'Compare custody balances with the ledger; halt withdrawals of any asset that is off.';

    public function handle(ReconcileLedger $reconcile, AssetCatalog $assets): int
    {
        $symbol = $this->argument('asset');
        $assetId = is_string($symbol) ? $assets->findBySymbol($symbol)->id : null;
        $symbols = [];
        foreach ($assets->all() as $asset) {
            $symbols[$asset->id] = $asset->symbol;
        }

        $reports = $reconcile->handle($assetId);

        $this->table(['Asset', 'Status', 'Ledger custody', 'Reported', 'Difference'], array_map(
            static fn (ReconciliationReport $r): array => [
                $symbols[$r->asset_id] ?? $r->asset_id,
                $r->status->value,
                $r->ledger_custody->toString(),
                $r->reported_custody?->toString() ?? '—',
                $r->difference?->toString() ?? '—',
            ],
            $reports,
        ));

        $mismatches = array_filter($reports, static fn (ReconciliationReport $r): bool => $r->status === ReconciliationStatus::Mismatch);

        return $mismatches === [] ? self::SUCCESS : self::FAILURE;
    }
}
