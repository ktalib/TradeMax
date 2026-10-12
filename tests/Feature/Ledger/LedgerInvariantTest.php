<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ledger\Actions\CheckLedgerInvariants;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\InvariantViolation;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Events\LedgerInvariantViolated;
use App\Domain\Ledger\Services\LedgerInvariants;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/*
| The invariants of PLAN §3.2. Each case breaks the ledger behind LedgerService's back,
| the way a bug or a manual SQL edit would, and checks it gets caught.
*/

beforeEach(function (): void {
    $this->btc = createAsset('BTC', 8);
    $this->user = User::factory()->create();
    deposit($this->user->id, $this->btc, '2');
    $this->wallet = userWallet($this->user->id, $this->btc);
});

function rules(): array
{
    return array_map(fn (InvariantViolation $v): string => $v->rule, app(LedgerInvariants::class)->check());
}

it('holds for a ledger written only through LedgerService', function (): void {
    postJournal([EntryDTO::debit($this->wallet, '0.5'), EntryDTO::credit(systemWallet($this->btc, WalletType::SystemFee), '0.5')]);

    expect(rules())->toBe([]);
});

it('catches a balance written directly to a wallet', function (): void {
    DB::table('wallets')->where('id', $this->wallet->id)->update(['available' => '5']);

    expect(rules())->toBe([InvariantViolation::BALANCE_DRIFT]);
});

it('catches a user wallet below zero', function (): void {
    DB::table('wallets')->where('id', $this->wallet->id)->update(['available' => '-1']);

    expect(rules())->toContain(InvariantViolation::NEGATIVE_BALANCE);
});

it('allows custody counter-accounts to be negative', function (): void {
    expect(balanceOf(systemWallet($this->btc))->available->isNegative())->toBeTrue()
        ->and(rules())->toBe([]);
});

it('catches an unbalanced journal and asset', function (): void {
    $entry = DB::table('journal_entries')->where('wallet_id', $this->wallet->id)->first();
    DB::table('journal_entries')->insert([
        'journal_id' => $entry->journal_id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->btc->id,
        'direction' => 'credit',
        'balance_type' => 'available',
        'amount' => '1',
        'balance_after' => '3',
        'created_at' => now(),
    ]);

    expect(rules())->toContain(InvariantViolation::UNBALANCED_JOURNAL)
        ->toContain(InvariantViolation::UNBALANCED_ASSET);
});

it('catches an entry booked to the wrong asset', function (): void {
    $eth = createAsset('ETH', 18);
    DB::table('journal_entries')->where('wallet_id', $this->wallet->id)->update(['asset_id' => $eth->id]);

    expect(rules())->toContain(InvariantViolation::ASSET_MISMATCH);
});

it('logs and raises an alert when violated', function (): void {
    Event::fake([LedgerInvariantViolated::class]);
    Log::spy();
    DB::table('wallets')->where('id', $this->wallet->id)->update(['available' => '5']);

    $violations = app(CheckLedgerInvariants::class)->handle();

    expect($violations)->toHaveCount(1);
    Event::assertDispatched(LedgerInvariantViolated::class, fn (LedgerInvariantViolated $e): bool => count($e->violations) === 1);
    Log::shouldHaveReceived('critical')->withArgs(fn (string $message): bool => str_contains($message, 'invariants'))->once();
});

it('stays quiet when the ledger is sound', function (): void {
    Event::fake([LedgerInvariantViolated::class]);

    expect(app(CheckLedgerInvariants::class)->handle())->toBe([]);
    Event::assertNotDispatched(LedgerInvariantViolated::class);
});

it('reports through the console command with a failing exit code', function (): void {
    $this->artisan('ledger:invariant-check')->expectsOutputToContain('hold')->assertSuccessful();

    DB::table('wallets')->where('id', $this->wallet->id)->update(['available' => '5']);

    $this->artisan('ledger:invariant-check', ['--since' => now()->subDay()->toIso8601String()])
        ->expectsOutputToContain('balance_drift')
        ->assertFailed();
});
