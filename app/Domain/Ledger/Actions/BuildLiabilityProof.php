<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Ledger\Models\LiabilityProof;
use App\Domain\Ledger\Models\WalletBalanceSnapshot;
use App\Domain\Ledger\Repositories\Contracts\LedgerTotals;
use App\Domain\Ledger\Services\MerkleSumTree;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds the proof-of-liabilities tree for one asset from the end-of-day snapshots of
 * $date. Publishing the root is a separate staff decision (Phase 10). One proof per
 * asset and day; re-running returns the existing proof.
 */
final class BuildLiabilityProof
{
    public function __construct(
        private readonly AssetCatalog $assets,
        private readonly LedgerTotals $totals,
        private readonly MerkleSumTree $tree,
    ) {}

    public function handle(int $assetId, CarbonImmutable $date): LiabilityProof
    {
        $asset = $this->assets->find($assetId);
        $day = $date->utc()->startOfDay();

        $existing = LiabilityProof::query()->where('asset_id', $asset->id)->whereDate('snapshot_date', $day)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (! WalletBalanceSnapshot::query()->whereDate('snapshot_date', $day)->exists()) {
            throw new RuntimeException("No balance snapshots for {$day->toDateString()}; run ledger:snapshot first.");
        }

        $proofId = (string) Str::ulid();
        $leaves = [];
        $rows = [];

        foreach ($this->totals->userTotalsOn($asset->id, $day) as $userId => $balance) {
            if ($balance->isNegative()) {
                throw new RuntimeException("User {$userId} has a negative {$asset->symbol} total; run ledger:invariant-check.");
            }

            $nonce = bin2hex(random_bytes(16));
            $hash = MerkleSumTree::leafHash($proofId, $userId, $balance, $nonce);
            $rows[] = ['position' => count($leaves), 'user_id' => $userId, 'balance' => $balance->toDatabase(), 'nonce' => $nonce, 'leaf_hash' => $hash];
            $leaves[] = ['hash' => $hash, 'sum' => $balance];
        }

        $root = $this->tree->root($leaves);

        return DB::transaction(function () use ($proofId, $asset, $day, $root, $leaves, $rows): LiabilityProof {
            $proof = LiabilityProof::query()->create([
                'public_id' => $proofId,
                'asset_id' => $asset->id,
                'snapshot_date' => $day->toDateString(),
                'root_hash' => $root['hash'],
                'total' => $root['sum'],
                'leaf_count' => count($leaves),
            ]);

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('liability_proof_leaves')->insert(array_map(
                    static fn (array $row): array => ['liability_proof_id' => $proof->id, ...$row],
                    $chunk,
                ));
            }

            return $proof;
        });
    }
}
