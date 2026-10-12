<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Console;

use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Ledger\Actions\BuildLiabilityProof;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ProofOfLiabilitiesCommand extends Command
{
    protected $signature = 'ledger:proof-of-liabilities {asset : asset symbol} {--date= : snapshot day (Y-m-d); defaults to yesterday}';

    protected $description = 'Build the Merkle sum tree of user balances for one asset from a day\'s snapshots.';

    public function handle(BuildLiabilityProof $build, AssetCatalog $assets): int
    {
        $asset = $assets->findBySymbol((string) $this->argument('asset'));
        $option = $this->option('date');
        $date = is_string($option) && $option !== '' ? CarbonImmutable::parse($option, 'UTC') : CarbonImmutable::now('UTC')->subDay();

        $proof = $build->handle($asset->id, $date);

        $this->info("{$asset->symbol} {$proof->snapshot_date->toDateString()}: {$proof->leaf_count} users, total {$proof->total}, root {$proof->root_hash}");

        return self::SUCCESS;
    }
}
