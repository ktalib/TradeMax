<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ledger\Actions\TakeBalanceSnapshots;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\WalletRef;
use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Models\WalletBalanceSnapshot;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->btc = createAsset('BTC', 8);
    $this->user = User::factory()->create();
});

function snapshotOf(WalletRef $wallet, string $date): ?WalletBalanceSnapshot
{
    return WalletBalanceSnapshot::query()->where('wallet_id', $wallet->id)->whereDate('snapshot_date', $date)->first();
}

it('records each wallet as it stood at the end of the day', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 14:00:00', 'UTC'));
    deposit($this->user->id, $this->btc, '1');
    $wallet = userWallet($this->user->id, $this->btc);
    postJournal([EntryDTO::debit($wallet, '0.25'), EntryDTO::credit($wallet, '0.25', BalanceType::Locked)]);

    // Activity after midnight must not leak into the 10th.
    $this->travelTo(CarbonImmutable::parse('2026-10-11 00:00:30', 'UTC'));
    deposit($this->user->id, $this->btc, '5');

    $this->travelTo(CarbonImmutable::parse('2026-10-11 00:01:00', 'UTC'));
    $written = app(TakeBalanceSnapshots::class)->handle();

    $snapshot = snapshotOf($wallet, '2026-10-10');
    expect($written)->toBeGreaterThanOrEqual(2)
        ->and($snapshot?->available->toString())->toBe('0.75')
        ->and($snapshot?->locked->toString())->toBe('0.25')
        ->and(snapshotOf(systemWallet($this->btc), '2026-10-10')?->available->toString())->toBe('-1')
        ->and(balanceOf($wallet)->available->toString())->toBe('5.75');
});

it('writes one snapshot per wallet and day, however often it runs', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00:00', 'UTC'));
    deposit($this->user->id, $this->btc, '1');

    $this->travelTo(CarbonImmutable::parse('2026-10-11 00:01:00', 'UTC'));
    $first = app(TakeBalanceSnapshots::class)->handle();
    $second = app(TakeBalanceSnapshots::class)->handle();

    expect($first)->toBe(2)->and($second)->toBe(0)
        ->and(WalletBalanceSnapshot::query()->count())->toBe(2);
});

it('skips wallets opened after the day closed', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 08:00:00', 'UTC'));
    deposit($this->user->id, $this->btc, '1');

    expect(app(TakeBalanceSnapshots::class)->handle(CarbonImmutable::parse('2026-10-10', 'UTC')))->toBe(0);
});

it('runs from the console for a chosen day', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00:00', 'UTC'));
    deposit($this->user->id, $this->btc, '1');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));

    $this->artisan('ledger:snapshot', ['--date' => '2026-10-10'])->expectsOutput('Wrote 2 snapshots.')->assertSuccessful();

    expect(snapshotOf(userWallet($this->user->id, $this->btc), '2026-10-10')?->available->toString())->toBe('1');
});
