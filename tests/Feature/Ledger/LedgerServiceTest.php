<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\JournalDTO;
use App\Domain\Ledger\DTOs\PostedBy;
use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\PostedByType;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Events\JournalPosted;
use App\Domain\Ledger\Exceptions\IdempotencyConflict;
use App\Domain\Ledger\Exceptions\InsufficientBalance;
use App\Domain\Ledger\Exceptions\InvalidJournal;
use App\Domain\Ledger\Models\Journal;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Models\Wallet;
use App\Domain\Ledger\Services\LedgerService;
use App\Support\Money\Decimal;
use Illuminate\Support\Facades\Event;
use Tests\Support\LedgerAssertions;

beforeEach(function (): void {
    $this->btc = createAsset('BTC', 8);
    $this->user = User::factory()->create();
});

afterEach(fn () => LedgerAssertions::assertBalanced());

describe('posting', function (): void {
    it('credits a deposit to the user and debits custody', function (): void {
        $posted = deposit($this->user->id, $this->btc, '1.25000000');

        expect(balanceOf(userWallet($this->user->id, $this->btc))->available->toString())->toBe('1.25')
            ->and(balanceOf(systemWallet($this->btc))->available->toString())->toBe('-1.25')
            ->and($posted->replayed)->toBeFalse();

        $journal = Journal::query()->with('entries')->findOrFail($posted->id);
        expect($journal->public_id)->toBe($posted->publicId)
            ->and($journal->reference_type)->toBe('test')
            ->and($journal->posted_by_type)->toBe(PostedByType::System)
            ->and($journal->entries)->toHaveCount(2);
    });

    it('records each wallet balance after every line', function (): void {
        deposit($this->user->id, $this->btc, '2');
        $wallet = userWallet($this->user->id, $this->btc);

        // Lock 0.5 for an order: available -> locked on the same wallet.
        postJournal([
            EntryDTO::debit($wallet, '0.5'),
            EntryDTO::credit($wallet, '0.5', BalanceType::Locked),
        ]);

        $entries = JournalEntry::query()->where('wallet_id', $wallet->id)->orderBy('id')->get();

        expect($entries->map(fn (JournalEntry $e): string => $e->balance_type->value.':'.$e->balance_after->toString())->all())
            ->toBe(['available:2', 'available:1.5', 'locked:0.5'])
            ->and(balanceOf($wallet)->available->toString())->toBe('1.5')
            ->and(balanceOf($wallet)->locked->toString())->toBe('0.5')
            ->and(balanceOf($wallet)->total()->toString())->toBe('2');
    });

    it('settles a two-asset trade with fees in one journal', function (): void {
        $usdt = createAsset('USDT', 6);
        $seller = User::factory()->create();
        deposit($this->user->id, $usdt, '50000');
        deposit($seller->id, $this->btc, '1');

        $buyerUsdt = userWallet($this->user->id, $usdt);
        $buyerBtc = userWallet($this->user->id, $this->btc);
        $sellerUsdt = userWallet($seller->id, $usdt);
        $sellerBtc = userWallet($seller->id, $this->btc);

        // Buyer pays 30,000 USDT for 0.5 BTC; each side pays 0.1% fee in what they receive.
        postJournal([
            EntryDTO::debit($buyerUsdt, '30000'),
            EntryDTO::credit($sellerUsdt, '29970'),
            EntryDTO::credit(systemWallet($usdt, WalletType::SystemFee), '30'),
            EntryDTO::debit($sellerBtc, '0.5'),
            EntryDTO::credit($buyerBtc, '0.4995'),
            EntryDTO::credit(systemWallet($this->btc, WalletType::SystemFee), '0.0005'),
        ]);

        expect(balanceOf($buyerUsdt)->available->toString())->toBe('20000')
            ->and(balanceOf($buyerBtc)->available->toString())->toBe('0.4995')
            ->and(balanceOf($sellerUsdt)->available->toString())->toBe('29970')
            ->and(balanceOf($sellerBtc)->available->toString())->toBe('0.5')
            ->and(balanceOf(systemWallet($usdt, WalletType::SystemFee))->available->toString())->toBe('30')
            ->and(balanceOf(systemWallet($this->btc, WalletType::SystemFee))->available->toString())->toBe('0.0005');
    });

    it('keeps 18-decimal amounts exact', function (): void {
        $eth = createAsset('ETH', 18);

        foreach (range(1, 3) as $i) {
            deposit($this->user->id, $eth, '0.000000000000000001');
        }
        deposit($this->user->id, $eth, '123456789.123456789123456789');

        expect(balanceOf(userWallet($this->user->id, $eth))->available->toDatabase())->toBe('123456789.123456789123456792');
    });

    it('fires JournalPosted once, after commit', function (): void {
        Event::fake([JournalPosted::class]);

        $posted = deposit($this->user->id, $this->btc, '1', 'dep-1');
        deposit($this->user->id, $this->btc, '1', 'dep-1');

        Event::assertDispatchedTimes(JournalPosted::class, 1);
        Event::assertDispatched(JournalPosted::class, fn (JournalPosted $e): bool => $e->journalId === $posted->id && count($e->walletIds) === 2);
    });
});

describe('insufficient funds', function (): void {
    it('refuses to take a user wallet below zero and writes nothing', function (): void {
        deposit($this->user->id, $this->btc, '1');
        $wallet = userWallet($this->user->id, $this->btc);
        $journals = Journal::query()->count();

        try {
            postJournal([EntryDTO::debit($wallet, '1.00000001'), EntryDTO::credit(systemWallet($this->btc), '1.00000001')]);
            $this->fail('Expected InsufficientBalance.');
        } catch (InsufficientBalance $e) {
            expect($e->walletId)->toBe($wallet->id)
                ->and($e->balance->toString())->toBe('1')
                ->and($e->required->toString())->toBe('1.00000001');
        }

        expect(Journal::query()->count())->toBe($journals)
            ->and(balanceOf($wallet)->available->toString())->toBe('1')
            ->and(balanceOf(systemWallet($this->btc))->available->toString())->toBe('-1');
    });

    it('checks the locked balance separately', function (): void {
        deposit($this->user->id, $this->btc, '1');
        $wallet = userWallet($this->user->id, $this->btc);

        postJournal([EntryDTO::debit($wallet, '0.4', BalanceType::Locked), EntryDTO::credit($wallet, '0.4')]);
    })->throws(InsufficientBalance::class);

    it('lets custody counter-accounts go negative', function (WalletType $type): void {
        postJournal([EntryDTO::debit(systemWallet($this->btc, $type), '5'), EntryDTO::credit(userWallet($this->user->id, $this->btc), '5')]);

        expect(balanceOf(systemWallet($this->btc, $type))->available->toString())->toBe('-5');
    })->with([WalletType::SystemHot, WalletType::SystemCold, WalletType::SystemDemoFaucet]);

    it('never lets fee, yield or suspense wallets go negative', function (WalletType $type): void {
        postJournal([EntryDTO::debit(systemWallet($this->btc, $type), '0.1'), EntryDTO::credit(userWallet($this->user->id, $this->btc), '0.1')]);
    })->with([WalletType::SystemFee, WalletType::SystemYieldPool, WalletType::SystemSuspense])->throws(InsufficientBalance::class);

    it('pays out of the fee wallet when it holds enough', function (): void {
        $fee = systemWallet($this->btc, WalletType::SystemFee);
        deposit($this->user->id, $this->btc, '1');
        postJournal([EntryDTO::debit(userWallet($this->user->id, $this->btc), '0.2'), EntryDTO::credit($fee, '0.2')]);

        // e.g. a referral commission paid from fees
        postJournal([EntryDTO::debit($fee, '0.05'), EntryDTO::credit(userWallet($this->user->id, $this->btc), '0.05')]);

        expect(balanceOf($fee)->available->toString())->toBe('0.15');
    });
});

describe('idempotency', function (): void {
    it('returns the original journal on replay and moves money once', function (): void {
        $first = deposit($this->user->id, $this->btc, '1', 'chain:tx-1');
        $again = deposit($this->user->id, $this->btc, '1', 'chain:tx-1');

        expect($again->id)->toBe($first->id)
            ->and($again->replayed)->toBeTrue()
            ->and(Journal::query()->where('idempotency_key', 'chain:tx-1')->count())->toBe(1)
            ->and(balanceOf(userWallet($this->user->id, $this->btc))->available->toString())->toBe('1');
    });

    it('returns the replay even when the first post spent the funds', function (): void {
        deposit($this->user->id, $this->btc, '1');
        $wallet = userWallet($this->user->id, $this->btc);
        $withdraw = fn () => postJournal([EntryDTO::debit($wallet, '1'), EntryDTO::credit(systemWallet($this->btc), '1')], 'withdrawal:1');

        $first = $withdraw();

        expect($withdraw()->id)->toBe($first->id);
    });

    it('refuses a reused key with different entries', function (): void {
        deposit($this->user->id, $this->btc, '1', 'chain:tx-2');
        deposit($this->user->id, $this->btc, '2', 'chain:tx-2');
    })->throws(IdempotencyConflict::class);
});

describe('malformed journals', function (): void {
    it('rejects them before touching the database', function (Closure $entries, string $message): void {
        $journals = Journal::query()->count();

        expect(fn () => postJournal($entries($this)))->toThrow(InvalidJournal::class, $message)
            ->and(Journal::query()->count())->toBe($journals);
    })->with([
        'one entry' => [fn ($t) => [EntryDTO::credit(userWallet($t->user->id, $t->btc), '1')], 'at least two'],
        'zero amount' => [fn ($t) => [EntryDTO::debit(systemWallet($t->btc), '0'), EntryDTO::credit(userWallet($t->user->id, $t->btc), '0')], 'greater than zero'],
        'negative amount' => [fn ($t) => [EntryDTO::debit(systemWallet($t->btc), '-1'), EntryDTO::credit(userWallet($t->user->id, $t->btc), '-1')], 'greater than zero'],
        'unbalanced' => [fn ($t) => [EntryDTO::debit(systemWallet($t->btc), '1'), EntryDTO::credit(userWallet($t->user->id, $t->btc), '1.1')], 'unbalanced'],
        'beyond asset precision' => [fn ($t) => [EntryDTO::debit(systemWallet($t->btc), '0.000000001'), EntryDTO::credit(userWallet($t->user->id, $t->btc), '0.000000001')], 'more than 8 decimals'],
        'unknown wallet' => [fn ($t) => [new EntryDTO(999999, $t->btc->id, EntryDirection::Debit, Decimal::of('1')), EntryDTO::credit(userWallet($t->user->id, $t->btc), '1')], 'does not exist'],
        'wrong asset for wallet' => [function ($t) {
            $eth = createAsset('ETH', 18);

            return [
                new EntryDTO(systemWallet($t->btc)->id, $eth->id, EntryDirection::Debit, Decimal::of('1')),
                EntryDTO::credit(userWallet($t->user->id, $eth), '1'),
            ];
        }, 'holds asset'],
    ]);

    it('needs an idempotency key', function (): void {
        app(LedgerService::class)->post(new JournalDTO('test', '1', ' ', 'x', [
            EntryDTO::debit(systemWallet($this->btc), '1'),
            EntryDTO::credit(userWallet($this->user->id, $this->btc), '1'),
        ], PostedBy::system()));
    })->throws(InvalidJournal::class, 'idempotency key');
});

describe('immutability', function (): void {
    it('refuses balance changes made through the model', function (): void {
        $wallet = Wallet::query()->findOrFail(userWallet($this->user->id, $this->btc)->id);

        $wallet->forceFill(['available' => '100'])->save();
    })->throws(LogicException::class, 'LedgerService');

    it('never edits or deletes journals and entries', function (): void {
        $posted = deposit($this->user->id, $this->btc, '1');
        $journal = Journal::query()->findOrFail($posted->id);
        $entry = JournalEntry::query()->where('journal_id', $posted->id)->firstOrFail();

        expect(fn () => $journal->update(['description' => 'edited']))->toThrow(LogicException::class)
            ->and(fn () => $journal->delete())->toThrow(LogicException::class)
            ->and(fn () => $entry->delete())->toThrow(LogicException::class)
            ->and(fn () => Wallet::query()->firstOrFail()->delete())->toThrow(LogicException::class);
    });
});

it('keeps every asset at zero net across random activity', function (): void {
    $users = User::factory()->count(4)->create();
    $usdt = createAsset('USDT', 6);
    mt_srand(20261012);

    foreach ($users as $user) {
        deposit($user->id, $this->btc, '3');
        deposit($user->id, $usdt, '1000');
    }

    for ($i = 0; $i < 60; $i++) {
        $from = $users[mt_rand(0, 3)];
        $to = $users[mt_rand(0, 3)];
        $asset = mt_rand(0, 1) === 0 ? $this->btc : $usdt;
        $amount = Decimal::of((string) mt_rand(1, 500))->div($asset->symbol === 'BTC' ? '1000' : '1')->toFixed($asset->precision);

        try {
            postJournal([
                EntryDTO::debit(userWallet($from->id, $asset), $amount),
                EntryDTO::credit(userWallet($to->id, $asset), $amount),
            ]);
        } catch (InsufficientBalance) {
            // expected sometimes; nothing must have been written
        }
    }

    foreach ([$this->btc, $usdt] as $asset) {
        $sum = Wallet::query()->where('asset_id', $asset->id)->get()
            ->reduce(fn (Decimal $carry, Wallet $w): Decimal => $carry->add($w->available)->add($w->locked), Decimal::zero());

        expect($sum->isZero())->toBeTrue();
    }
});
