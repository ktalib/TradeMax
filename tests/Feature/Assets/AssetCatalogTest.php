<?php

declare(strict_types=1);

use App\Domain\Assets\Actions\HaltAssetWithdrawals;
use App\Domain\Assets\Events\AssetWithdrawalsHalted;
use App\Domain\Assets\Exceptions\UnknownAsset;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetNetwork;
use App\Domain\Assets\Models\Network;
use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Identity\Models\User;
use App\Domain\Ledger\Actions\EnsureSystemWallets;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Models\Wallet;
use App\Domain\Ledger\Repositories\Contracts\Wallets;
use App\Support\Audit\AuditLog;
use Database\Seeders\AssetSeeder;
use Database\Seeders\SystemWalletSeeder;
use Illuminate\Support\Facades\Event;

describe('seeding', function (): void {
    it('seeds the launch assets, networks and routes', function (): void {
        $this->seed(AssetSeeder::class);

        expect(Asset::query()->orderBy('id')->pluck('symbol')->all())->toBe(['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'TRX', 'USDC'])
            ->and(Network::query()->pluck('code')->sort()->values()->all())->toBe(['BEP20', 'BTC', 'ERC20', 'SOL', 'TRC20'])
            ->and(AssetNetwork::query()->count())->toBe(12);

        $usdtTron = AssetNetwork::query()->with(['asset', 'network'])
            ->whereHas('asset', fn ($q) => $q->where('symbol', 'USDT'))
            ->whereHas('network', fn ($q) => $q->where('code', 'TRC20'))
            ->sole();

        expect($usdtTron->contract_address)->toBe('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t')
            ->and($usdtTron->withdraw_fee->toString())->toBe('1')
            ->and($usdtTron->confirmations())->toBe(19)
            ->and($usdtTron->network->explorerTxUrl('abc'))->toBe('https://tronscan.org/#/transaction/abc');
    });

    it('keeps admin edits when re-run', function (): void {
        $this->seed(AssetSeeder::class);
        AssetNetwork::query()->update(['withdraw_fee' => '9']);
        Asset::query()->where('symbol', 'BTC')->update(['is_active' => false]);

        $this->seed(AssetSeeder::class);

        expect(AssetNetwork::query()->where('withdraw_fee', '!=', '9')->count())->toBe(0)
            ->and(Asset::query()->where('symbol', 'BTC')->value('is_active'))->toBeFalsy();
    });

    it('creates every system wallet once', function (): void {
        $this->seed(AssetSeeder::class);
        $this->seed(SystemWalletSeeder::class);
        $this->seed(SystemWalletSeeder::class);

        expect(Wallet::query()->whereNull('user_id')->count())->toBe(7 * 5)
            ->and(Wallet::query()->where('type', WalletType::SystemDemoFaucet->value)->count())->toBe(0);
    });

    it('adds the demo faucet only in the demo environment', function (): void {
        config(['platform.demo' => true]);
        $btc = createAsset('BTC');

        expect(app(EnsureSystemWallets::class)->handle($btc->id))->toBe(6)
            ->and(Wallet::query()->where('type', WalletType::SystemDemoFaucet->value)->count())->toBe(1);
    });
});

describe('catalog', function (): void {
    it('finds assets by id or symbol, case-insensitively', function (): void {
        $btc = createAsset('BTC', 8);

        expect(app(AssetCatalog::class)->find($btc->id)->symbol)->toBe('BTC')
            ->and(app(AssetCatalog::class)->findBySymbol('btc')->precision)->toBe(8)
            ->and(app(AssetCatalog::class)->all())->toHaveCount(1);
    });

    it('throws for unknown assets', function (Closure $lookup): void {
        $lookup();
    })->with([
        'id' => [fn () => app(AssetCatalog::class)->find(999)],
        'symbol' => [fn () => app(AssetCatalog::class)->findBySymbol('DOGE')],
    ])->throws(UnknownAsset::class);
});

describe('halting withdrawals', function (): void {
    it('halts once, keeps the first reason and audits it', function (): void {
        Event::fake([AssetWithdrawalsHalted::class]);
        $btc = createAsset('BTC');

        expect(app(HaltAssetWithdrawals::class)->handle($btc->id, 'Node outage'))->toBeTrue()
            ->and(app(HaltAssetWithdrawals::class)->handle($btc->id, 'Second reason'))->toBeFalse()
            ->and(Asset::query()->findOrFail($btc->id)->withdrawals_halt_reason)->toBe('Node outage');

        Event::assertDispatchedTimes(AssetWithdrawalsHalted::class, 1);
        expect(AuditLog::query()->where('action', 'asset.withdrawals_halted')->sole()->actor_type)->toBe('system');
    });
});

describe('wallets', function (): void {
    it('creates a user wallet on first use and reuses it', function (): void {
        $btc = createAsset('BTC');
        $user = User::factory()->create();

        $first = app(Wallets::class)->forUser($user->id, $btc->id);
        $again = app(Wallets::class)->forUser($user->id, $btc->id);
        $staking = app(Wallets::class)->forUser($user->id, $btc->id, WalletType::Staking);

        expect($again->id)->toBe($first->id)
            ->and($staking->id)->not->toBe($first->id)
            ->and($first->userId)->toBe($user->id)
            ->and(Wallet::query()->count())->toBe(2);
    });

    it('lists a user\'s balances by asset', function (): void {
        $btc = createAsset('BTC');
        $user = User::factory()->create();
        deposit($user->id, $btc, '0.5');

        $balances = app(Wallets::class)->balancesForUser($user->id);

        expect(array_keys($balances))->toBe([$btc->id])
            ->and($balances[$btc->id]->available->toString())->toBe('0.5');
    });

    it('keeps user and system wallet types apart', function (Closure $call): void {
        $call(createAsset('BTC'));
    })->with([
        'system type for a user' => [fn ($btc) => app(Wallets::class)->forUser(1, $btc->id, WalletType::SystemFee)],
        'user type for the system' => [fn ($btc) => app(Wallets::class)->system($btc->id, WalletType::Spot)],
    ])->throws(InvalidArgumentException::class);
});
