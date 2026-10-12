<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Support\Money\Decimal;
use InvalidArgumentException;

/**
 * Merkle sum tree for proof of liabilities. Every node carries a hash and the sum of the
 * balances below it, and each parent hash commits to both children's sums, so the
 * platform can't hide a balance or make one count less than it is. A user checks their
 * leaf against the published root hash and total with the sibling path.
 *
 * Hashes are hex sha256. Sums are written at full Decimal scale so every verifier hashes
 * the same bytes.
 */
final class MerkleSumTree
{
    public static function leafHash(string $proofId, int $userId, Decimal $balance, string $nonce): string
    {
        return hash('sha256', implode('|', ['leaf', $proofId, (string) $userId, $balance->toDatabase(), $nonce]));
    }

    /**
     * @param  list<array{hash: string, sum: Decimal}>  $leaves
     * @return array{hash: string, sum: Decimal}
     */
    public function root(array $leaves): array
    {
        $levels = $this->levels($leaves);
        $top = end($levels);

        return $top !== false && isset($top[0]) ? $top[0] : self::padding();
    }

    /**
     * Sibling path from leaf $index up to the root.
     *
     * @param  list<array{hash: string, sum: Decimal}>  $leaves
     * @return list<array{hash: string, sum: Decimal, side: 'left'|'right'}>
     */
    public function path(array $leaves, int $index): array
    {
        if (! isset($leaves[$index])) {
            throw new InvalidArgumentException("No leaf at position {$index}.");
        }

        $path = [];
        foreach ($this->levels($leaves) as $level) {
            if (count($level) === 1) {
                break;
            }

            $siblingIndex = $index % 2 === 0 ? $index + 1 : $index - 1;
            $sibling = $level[$siblingIndex];
            $path[] = ['hash' => $sibling['hash'], 'sum' => $sibling['sum'], 'side' => $index % 2 === 0 ? 'right' : 'left'];
            $index = intdiv($index, 2);
        }

        return $path;
    }

    /**
     * @param  list<array{hash: string, sum: Decimal, side: 'left'|'right'}>  $path
     */
    public static function verify(string $leafHash, Decimal $leafSum, array $path, string $rootHash, Decimal $rootSum): bool
    {
        $node = ['hash' => $leafHash, 'sum' => $leafSum];

        foreach ($path as $sibling) {
            if ($sibling['sum']->isNegative()) {
                return false;
            }

            $node = $sibling['side'] === 'right'
                ? self::parent($node, $sibling)
                : self::parent($sibling, $node);
        }

        return hash_equals($rootHash, $node['hash']) && $node['sum']->eq($rootSum);
    }

    /**
     * @param  list<array{hash: string, sum: Decimal}>  $leaves
     * @return list<list<array{hash: string, sum: Decimal}>>
     */
    private function levels(array $leaves): array
    {
        if ($leaves === []) {
            return [[self::padding()]];
        }

        $levels = [$leaves];
        $level = $leaves;

        while (count($level) > 1) {
            if (count($level) % 2 === 1) {
                // Pad with a zero-sum node rather than duplicating, which would double-count.
                $level[] = self::padding();
                $levels[array_key_last($levels)] = $level;
            }

            $next = [];
            for ($i = 0; $i < count($level); $i += 2) {
                $next[] = self::parent($level[$i], $level[$i + 1]);
            }

            $levels[] = $next;
            $level = $next;
        }

        return $levels;
    }

    /**
     * @param  array{hash: string, sum: Decimal}  $left
     * @param  array{hash: string, sum: Decimal}  $right
     * @return array{hash: string, sum: Decimal}
     */
    private static function parent(array $left, array $right): array
    {
        return [
            'hash' => hash('sha256', implode('|', ['node', $left['hash'], $left['sum']->toDatabase(), $right['hash'], $right['sum']->toDatabase()])),
            'sum' => $left['sum']->add($right['sum']),
        ];
    }

    /**
     * @return array{hash: string, sum: Decimal}
     */
    private static function padding(): array
    {
        return ['hash' => hash('sha256', 'pad'), 'sum' => Decimal::zero()];
    }
}
