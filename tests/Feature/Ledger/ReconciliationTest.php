<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Assets\Actions\ResumeAssetWithdrawals;
use App\Domain\Assets\Events\AssetWithdrawalsHalted;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Identity\Models\User;
use App\Domain\Ledger\Actions\ReconcileLedger;
use App\Domain\Ledger\Actions\RecordCustodyBalance;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\Enums\CustodySource;
use App\Domain\Ledger\Enums\ReconciliationStatus;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Events\ReconciliationMismatchDetected;
use App\Support\Audit\AuditLog;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->btc = createAsset('BTC', 8, tolerance: '0.0001');
    $this->user = User::factory()->create();
    $this->admin = Admin::query()->create(['name' => 'Finance', 'email' => 'finance@example.com', 'password' => Hash::make('velvet-harbor-canyon-quill')]);
    $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00:00', 'UTC'));
});

function custody(int $assetId, string $balance, int $adminId, ?CarbonImmutable $asOf = null): void
{
    app(RecordCustodyBalance::class)->handle($assetId, Decimal::of($balance), $asOf ?? CarbonImmutable::now(), CustodySource::Manual, $adminId);
}

it('reports an asset with no custody figure as missing without halting it', function (): void {
    deposit($this->user->id, $this->btc, '1');

    [$report] = app(ReconcileLedger::class)->handle($this->btc->id);

    expect($report->status)->toBe(ReconciliationStatus::Missing)
        ->and($report->ledger_custody->toString())->toBe('1')
        ->and($report->withdrawals_halted)->toBeFalse()
        ->and(app(AssetCatalog::class)->find($this->btc->id)->withdrawalsHalted)->toBeFalse();
});

it('matches when custody holds what the ledger owes, hot and cold together', function (): void {
    deposit($this->user->id, $this->btc, '3');
    // Sweep 2 BTC from hot to cold storage.
    postJournal([EntryDTO::debit(systemWallet($this->btc, WalletType::SystemCold), '2'), EntryDTO::credit(systemWallet($this->btc), '2')]);
    custody($this->btc->id, '3.00005', $this->admin->id);

    [$report] = app(ReconcileLedger::class)->handle($this->btc->id);

    expect($report->status)->toBe(ReconciliationStatus::Matched)
        ->and($report->ledger_custody->toString())->toBe('3')
        ->and($report->difference?->toString())->toBe('0.00005');
});

it('compares against the ledger at the moment custody was counted', function (): void {
    deposit($this->user->id, $this->btc, '1');
    custody($this->btc->id, '1', $this->admin->id);

    // Deposits after the count are not in the custody figure, and must not look like a gap.
    $this->travel(30)->minutes();
    deposit($this->user->id, $this->btc, '4');

    [$report] = app(ReconcileLedger::class)->handle($this->btc->id);

    expect($report->status)->toBe(ReconciliationStatus::Matched)
        ->and($report->ledger_custody->toString())->toBe('1');
});

it('halts withdrawals and raises the alarm on a mismatch', function (): void {
    Event::fake([ReconciliationMismatchDetected::class, AssetWithdrawalsHalted::class]);
    deposit($this->user->id, $this->btc, '2');
    custody($this->btc->id, '1.5', $this->admin->id);

    [$report] = app(ReconcileLedger::class)->handle();

    expect($report->status)->toBe(ReconciliationStatus::Mismatch)
        ->and($report->difference?->toString())->toBe('-0.5')
        ->and($report->withdrawals_halted)->toBeTrue()
        ->and(app(AssetCatalog::class)->find($this->btc->id)->withdrawalsHalted)->toBeTrue()
        ->and(Asset::query()->findOrFail($this->btc->id)->withdrawals_halt_reason)->toContain('Reconciliation mismatch -0.5 BTC');

    Event::assertDispatched(ReconciliationMismatchDetected::class, fn ($e): bool => $e->symbol === 'BTC' && $e->difference === '-0.5');
    Event::assertDispatched(AssetWithdrawalsHalted::class);
    expect(AuditLog::query()->where('action', 'asset.withdrawals_halted')->count())->toBe(1);
});

it('uses the newest custody figure', function (): void {
    deposit($this->user->id, $this->btc, '2');
    custody($this->btc->id, '1', $this->admin->id, CarbonImmutable::now()->subHour());
    custody($this->btc->id, '2', $this->admin->id);

    expect(app(ReconcileLedger::class)->handle($this->btc->id)[0]->status)->toBe(ReconciliationStatus::Matched);
});

it('lets staff resume withdrawals once resolved, on the record', function (): void {
    deposit($this->user->id, $this->btc, '2');
    custody($this->btc->id, '1', $this->admin->id);
    app(ReconcileLedger::class)->handle();

    app(ResumeAssetWithdrawals::class)->handle($this->btc->id, $this->admin, 'Custody recount: 2 BTC');

    expect(app(AssetCatalog::class)->find($this->btc->id)->withdrawalsHalted)->toBeFalse();
    $log = AuditLog::query()->where('action', 'asset.withdrawals_resumed')->sole();
    expect($log->actor_type)->toBe('admin')->and($log->after)->toBe(['note' => 'Custody recount: 2 BTC']);
});

describe('recording custody', function (): void {
    it('stores the figure at asset precision and audits it', function (): void {
        app(RecordCustodyBalance::class)->handle($this->btc->id, Decimal::of('1.123456789'), CarbonImmutable::now(), CustodySource::Manual, $this->admin->id, 'Ledger wallet statement');

        $log = AuditLog::query()->where('action', 'ledger.custody_recorded')->sole();
        expect($log->after['balance'])->toBe('1.12345678')->and($log->after['asset'])->toBe('BTC');
    });

    it('refuses impossible figures', function (Closure $call, string $message): void {
        expect(fn () => $call($this))->toThrow(InvalidArgumentException::class, $message);
    })->with([
        'negative' => [fn ($t) => app(RecordCustodyBalance::class)->handle($t->btc->id, Decimal::of('-1'), CarbonImmutable::now(), CustodySource::Manual, $t->admin->id), 'negative'],
        'future' => [fn ($t) => app(RecordCustodyBalance::class)->handle($t->btc->id, Decimal::of('1'), CarbonImmutable::now()->addDay(), CustodySource::Manual, $t->admin->id), 'future'],
        'anonymous manual entry' => [fn ($t) => app(RecordCustodyBalance::class)->handle($t->btc->id, Decimal::of('1'), CarbonImmutable::now(), CustodySource::Manual), 'staff member'],
    ]);
});

it('prints a report and fails the command on a mismatch', function (): void {
    deposit($this->user->id, $this->btc, '2');
    custody($this->btc->id, '2', $this->admin->id);
    $this->artisan('ledger:reconcile', ['asset' => 'btc'])->expectsOutputToContain('matched')->assertSuccessful();

    custody($this->btc->id, '1', $this->admin->id);
    $this->artisan('ledger:reconcile')->expectsOutputToContain('mismatch')->assertFailed();
});
