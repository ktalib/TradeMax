<?php

declare(strict_types=1);

use App\Domain\Ledger\Services\MerkleSumTree;
use App\Support\Money\Decimal;

/**
 * @return list<array{hash: string, sum: Decimal}>
 */
function leaves(int $count): array
{
    return array_map(static fn (int $i): array => [
        'hash' => MerkleSumTree::leafHash('proof', $i, Decimal::of((string) $i), str_repeat('a', 32)),
        'sum' => Decimal::of((string) $i),
    ], range(1, $count));
}

it('proves every leaf for any tree size', function (int $count): void {
    $tree = new MerkleSumTree;
    $leaves = leaves($count);
    $root = $tree->root($leaves);

    expect($root['sum']->toString())->toBe((string) ($count * ($count + 1) / 2));

    foreach ($leaves as $i => $leaf) {
        expect(MerkleSumTree::verify($leaf['hash'], $leaf['sum'], $tree->path($leaves, $i), $root['hash'], $root['sum']))->toBeTrue();
    }
})->with([1, 2, 3, 5, 8, 13]);

it('rejects a wrong balance, a wrong root or a wrong total', function (): void {
    $tree = new MerkleSumTree;
    $leaves = leaves(6);
    $root = $tree->root($leaves);
    $path = $tree->path($leaves, 2);

    expect(MerkleSumTree::verify($leaves[2]['hash'], Decimal::of('2'), $path, $root['hash'], $root['sum']))->toBeFalse()
        ->and(MerkleSumTree::verify($leaves[2]['hash'], $leaves[2]['sum'], $path, str_repeat('0', 64), $root['sum']))->toBeFalse()
        ->and(MerkleSumTree::verify($leaves[2]['hash'], $leaves[2]['sum'], $path, $root['hash'], Decimal::of('1')))->toBeFalse();
});

it('rejects a path that hides liabilities behind a negative sum', function (): void {
    $tree = new MerkleSumTree;
    $leaves = leaves(2);
    $path = $tree->path($leaves, 0);
    $path[0]['sum'] = Decimal::of('-2');

    expect(MerkleSumTree::verify($leaves[0]['hash'], $leaves[0]['sum'], $path, $tree->root($leaves)['hash'], Decimal::of('-1')))->toBeFalse();
});

it('has a zero-total root when nobody holds the asset', function (): void {
    expect((new MerkleSumTree)->root([])['sum']->isZero())->toBeTrue();
});
