<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ledger\Actions\BuildLiabilityProof;
use App\Domain\Ledger\Actions\TakeBalanceSnapshots;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Models\LiabilityProof;
use App\Domain\Ledger\Services\LiabilityInclusion;
use App\Domain\Ledger\Services\MerkleSumTree;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->btc = createAsset('BTC', 8);
    $this->day = CarbonImmutable::parse('2026-10-10', 'UTC');
    $this->users = User::factory()->count(5)->create();

    $this->travelTo($this->day->setTime(12, 0));
    foreach ($this->users->values() as $i => $user) {
        deposit($user->id, $this->btc, (string) ($i + 1));
    }

    // Balances in other wallet types and locked funds count too; system wallets don't.
    $first = $this->users->first();
    postJournal([EntryDTO::debit(userWallet($first->id, $this->btc), '0.5'), EntryDTO::credit(userWallet($first->id, $this->btc, WalletType::Staking), '0.5')]);
    postJournal([EntryDTO::debit(userWallet($first->id, $this->btc), '0.2'), EntryDTO::credit(userWallet($first->id, $this->btc), '0.2', BalanceType::Locked)]);
    postJournal([EntryDTO::debit(userWallet($first->id, $this->btc), '0.1'), EntryDTO::credit(systemWallet($this->btc, WalletType::SystemFee), '0.1')]);

    $this->travelTo($this->day->addDay()->setTime(0, 5));
    app(TakeBalanceSnapshots::class)->handle($this->day);
});

it('commits to the total owed to users of an asset', function (): void {
    $proof = app(BuildLiabilityProof::class)->handle($this->btc->id, $this->day);

    // 1 + 2 + 3 + 4 + 5, minus the 0.1 fee
    expect($proof->total->toString())->toBe('14.9')
        ->and($proof->leaf_count)->toBe(5)
        ->and($proof->root_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($proof->published_at)->toBeNull();
});

it('lets every user verify their own balance against the root', function (): void {
    $proof = app(BuildLiabilityProof::class)->handle($this->btc->id, $this->day);

    foreach ($this->users as $user) {
        $inclusion = app(LiabilityInclusion::class)->for($proof, $user->id);

        expect($inclusion)->not->toBeNull()
            ->and(MerkleSumTree::leafHash($proof->public_id, $user->id, $inclusion['balance'], $inclusion['nonce']))->toBe($inclusion['leaf_hash'])
            ->and(MerkleSumTree::verify($inclusion['leaf_hash'], $inclusion['balance'], $inclusion['path'], $proof->root_hash, $proof->total))->toBeTrue();
    }

    expect(app(LiabilityInclusion::class)->for($proof, $this->users->first()->id)['balance']->toString())->toBe('0.9');
});

it('detects a balance shown lower than it is', function (): void {
    $proof = app(BuildLiabilityProof::class)->handle($this->btc->id, $this->day);
    $inclusion = app(LiabilityInclusion::class)->for($proof, $this->users->last()->id);

    expect(MerkleSumTree::verify($inclusion['leaf_hash'], Decimal::of('1'), $inclusion['path'], $proof->root_hash, $proof->total))->toBeFalse();
});

it('builds one proof per asset and day', function (): void {
    $first = app(BuildLiabilityProof::class)->handle($this->btc->id, $this->day);
    $second = app(BuildLiabilityProof::class)->handle($this->btc->id, $this->day);

    expect($second->id)->toBe($first->id)->and(LiabilityProof::query()->count())->toBe(1);
});

it('needs the day to be snapshotted first', function (): void {
    app(BuildLiabilityProof::class)->handle($this->btc->id, $this->day->subDays(3));
})->throws(RuntimeException::class, 'run ledger:snapshot first');

it('has no leaf for a user who holds none of the asset', function (): void {
    $proof = app(BuildLiabilityProof::class)->handle($this->btc->id, $this->day);

    expect(app(LiabilityInclusion::class)->for($proof, User::factory()->create()->id))->toBeNull();
});

it('runs from the console', function (): void {
    $this->artisan('ledger:proof-of-liabilities', ['asset' => 'BTC', '--date' => '2026-10-10'])
        ->expectsOutputToContain('5 users, total 14.9')
        ->assertSuccessful();
});
