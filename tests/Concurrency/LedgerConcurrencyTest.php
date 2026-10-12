<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ledger\DTOs\WalletRef;
use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Exceptions\InsufficientBalance;
use App\Domain\Ledger\Models\Journal;
use Tests\Support\LedgerAssertions;
use Tests\Support\ParallelRunner;

/*
| Real parallel PHP processes against MySQL (AGENTS.md: proc_open, no pcntl). Fixtures
| are committed so the workers can see them; tables are truncated between tests.
*/

const WORKERS = 10;

beforeEach(function (): void {
    if (config('database.default') !== 'mysql') {
        $this->markTestSkipped('Concurrency tests need MySQL row locks.');
    }
});

afterEach(function (): void {
    LedgerAssertions::assertBalanced();
    $this->truncateTablesForAllConnections();
});

/**
 * @param  list<array{key: string, reference?: string, entries: list<array{WalletRef, string, string}>}>  $jobs  entries: [wallet, direction, amount]
 * @return list<array{ok: bool, began: float, ended: float, journal_id?: int, replayed?: bool, error?: string, message?: string}>
 */
function runInParallel(array $jobs): array
{
    $argumentSets = array_map(static fn (array $job, int $i): array => [base64_encode((string) json_encode([
        'key' => $job['key'],
        'reference' => $job['reference'] ?? (string) $i,
        'entries' => array_map(static fn (array $e): array => [
            'wallet' => $e[0]->id,
            'asset' => $e[0]->assetId,
            'direction' => $e[1],
            'amount' => $e[2],
            'balance_type' => BalanceType::Available->value,
        ], $job['entries']),
    ]))], $jobs, array_keys($jobs));

    $results = ParallelRunner::run(base_path('tests/Support/workers/post-journal.php'), $argumentSets, [
        'LEDGER_WORKER_DATABASE' => (string) config('database.connections.mysql.database'),
    ]);

    return array_map(static function (array $r): array {
        $decoded = json_decode($r['stdout'], true);
        expect($r['exit'])->toBe(0, $r['stderr'].$r['stdout'])
            ->and($decoded)->toBeArray();

        /** @var array{ok: bool, began: float, ended: float, journal_id?: int, replayed?: bool, error?: string, message?: string} $decoded */
        return $decoded;
    }, $results);
}

it('never double-spends: parallel withdrawals stop at the balance', function (): void {
    $btc = createAsset('BTC', 8);
    $user = User::factory()->create();
    deposit($user->id, $btc, '1');
    $wallet = userWallet($user->id, $btc);
    $hot = systemWallet($btc);

    // Ten attempts to send 0.3 BTC from a 1 BTC balance at the same moment.
    $results = runInParallel(array_map(fn (int $i): array => [
        'key' => "withdrawal:{$i}",
        'entries' => [[$wallet, 'debit', '0.3'], [$hot, 'credit', '0.3']],
    ], range(1, WORKERS)));

    // Proof the attempts really raced: at least half the workers were in flight together.
    $firstDone = min(array_column($results, 'ended'));
    expect(count(array_filter(array_column($results, 'began'), fn (float $t): bool => $t < $firstDone)))->toBeGreaterThanOrEqual(intdiv(WORKERS, 2));

    $succeeded = array_filter($results, fn (array $r): bool => $r['ok']);
    $failed = array_filter($results, fn (array $r): bool => ! $r['ok']);

    expect($succeeded)->toHaveCount(3)
        ->and(array_unique(array_column($failed, 'error')))->toBe([InsufficientBalance::class])
        ->and(balanceOf($wallet)->available->toString())->toBe('0.1')
        ->and(balanceOf($hot)->available->toString())->toBe('-0.1');
});

it('posts a replayed key once, however many workers race with it', function (): void {
    $btc = createAsset('BTC', 8);
    $user = User::factory()->create();
    $wallet = userWallet($user->id, $btc);
    $hot = systemWallet($btc);

    // The same deposit webhook delivered ten times at once.
    $results = runInParallel(array_fill(0, WORKERS, [
        'key' => 'chain:BTC:abc123',
        'reference' => 'abc123',
        'entries' => [[$hot, 'debit', '0.75'], [$wallet, 'credit', '0.75']],
    ]));

    expect(array_column($results, 'ok'))->each->toBeTrue()
        ->and(array_unique(array_column($results, 'journal_id')))->toHaveCount(1)
        ->and(array_filter(array_column($results, 'replayed'), fn (bool $r): bool => ! $r))->toHaveCount(1)
        ->and(Journal::query()->count())->toBe(1)
        ->and(balanceOf($wallet)->available->toString())->toBe('0.75');
});

it('lets every market write the hot system wallets at once without losing an update', function (): void {
    $btc = createAsset('BTC', 8);
    $hot = systemWallet($btc);
    $fee = systemWallet($btc, WalletType::SystemFee);
    $users = User::factory()->count(WORKERS)->create();

    // Ten deposits for ten users, each taking a deposit fee, all touching system_hot and system_fee.
    $results = runInParallel($users->values()->map(fn (User $u, int $i): array => [
        'key' => "deposit:{$i}",
        'entries' => [[$hot, 'debit', '1'], [userWallet($u->id, $btc), 'credit', '0.999'], [$fee, 'credit', '0.001']],
    ])->all());

    expect(array_column($results, 'ok'))->each->toBeTrue()
        ->and(balanceOf($hot)->available->toString())->toBe('-10')
        ->and(balanceOf($fee)->available->toString())->toBe('0.01');
});

it('does not deadlock when transfers cross between the same wallets', function (): void {
    $btc = createAsset('BTC', 8);
    [$a, $b] = User::factory()->count(2)->create()->all();
    deposit($a->id, $btc, '5');
    deposit($b->id, $btc, '5');
    $walletA = userWallet($a->id, $btc);
    $walletB = userWallet($b->id, $btc);

    // Half send A -> B, half B -> A: lock ordering by id keeps them from deadlocking.
    $results = runInParallel(array_map(fn (int $i): array => [
        'key' => "transfer:{$i}",
        'entries' => $i % 2 === 0
            ? [[$walletA, 'debit', '0.5'], [$walletB, 'credit', '0.5']]
            : [[$walletB, 'debit', '0.5'], [$walletA, 'credit', '0.5']],
    ], range(1, WORKERS)));

    expect(array_column($results, 'ok'))->each->toBeTrue()
        ->and(balanceOf($walletA)->available->toString())->toBe('5')
        ->and(balanceOf($walletB)->available->toString())->toBe('5');
});
