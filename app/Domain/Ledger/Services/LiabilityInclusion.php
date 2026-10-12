<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Models\LiabilityProof;
use App\Domain\Ledger\Models\LiabilityProofLeaf;
use App\Support\Money\Decimal;

/**
 * Everything a user needs to check that their balance is inside a published root.
 */
final class LiabilityInclusion
{
    public function __construct(private readonly MerkleSumTree $tree) {}

    /**
     * @return array{position: int, balance: Decimal, nonce: string, leaf_hash: string, path: list<array{hash: string, sum: Decimal, side: 'left'|'right'}>, root_hash: string, total: Decimal}|null
     */
    public function for(LiabilityProof $proof, int $userId): ?array
    {
        $leaves = LiabilityProofLeaf::query()
            ->where('liability_proof_id', $proof->id)
            ->orderBy('position')
            ->get();

        $mine = $leaves->firstWhere('user_id', $userId);
        if (! $mine instanceof LiabilityProofLeaf) {
            return null;
        }

        $nodes = array_values($leaves
            ->map(static fn (LiabilityProofLeaf $leaf): array => ['hash' => $leaf->leaf_hash, 'sum' => $leaf->balance])
            ->all());

        return [
            'position' => $mine->position,
            'balance' => $mine->balance,
            'nonce' => $mine->nonce,
            'leaf_hash' => $mine->leaf_hash,
            'path' => $this->tree->path($nodes, $mine->position),
            'root_hash' => $proof->root_hash,
            'total' => $proof->total,
        ];
    }
}
