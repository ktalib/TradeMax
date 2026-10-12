<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetNetwork;
use App\Domain\Assets\Models\Network;
use Illuminate\Database\Seeder;

/**
 * Launch assets, networks and how each asset moves on each network. Fees, minimums and
 * tolerances are starting values for the operator to review in the admin panel. Rows that
 * already exist are left alone so re-running never overwrites admin edits.
 */
final class AssetSeeder extends Seeder
{
    /**
     * symbol => [name, ledger precision, display precision, reconcile tolerance (~1 USD)]
     *
     * @var array<string, array{string, int, int, string}>
     */
    private const ASSETS = [
        'BTC' => ['Bitcoin', 8, 8, '0.00002'],
        'ETH' => ['Ethereum', 18, 6, '0.0005'],
        'USDT' => ['Tether USD', 6, 2, '1'],
        'BNB' => ['BNB', 18, 4, '0.002'],
        'SOL' => ['Solana', 9, 4, '0.01'],
        'TRX' => ['TRON', 6, 2, '5'],
        'USDC' => ['USD Coin', 6, 2, '1'],
    ];

    /**
     * code => [name, tx url, address url, confirmations]
     *
     * @var array<string, array{string, string, string, int}>
     */
    private const NETWORKS = [
        'BTC' => ['Bitcoin', 'https://mempool.space/tx/{tx}', 'https://mempool.space/address/{address}', 2],
        'ERC20' => ['Ethereum (ERC20)', 'https://etherscan.io/tx/{tx}', 'https://etherscan.io/address/{address}', 12],
        'BEP20' => ['BNB Smart Chain (BEP20)', 'https://bscscan.com/tx/{tx}', 'https://bscscan.com/address/{address}', 15],
        'TRC20' => ['TRON (TRC20)', 'https://tronscan.org/#/transaction/{tx}', 'https://tronscan.org/#/address/{address}', 19],
        'SOL' => ['Solana', 'https://solscan.io/tx/{tx}', 'https://solscan.io/account/{address}', 32],
    ];

    /**
     * [asset, network, contract (null = native), min deposit, min withdraw, withdraw fee]
     *
     * @var list<array{string, string, string|null, string, string, string}>
     */
    private const ROUTES = [
        ['BTC', 'BTC', null, '0.0001', '0.0005', '0.0002'],
        ['ETH', 'ERC20', null, '0.002', '0.005', '0.0015'],
        ['USDT', 'ERC20', '0xdAC17F958D2ee523a2206206994597C13D831ec7', '10', '20', '5'],
        ['USDT', 'TRC20', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', '1', '10', '1'],
        ['USDT', 'BEP20', '0x55d398326f99059fF775485246999027B3197955', '1', '10', '0.3'],
        ['USDT', 'SOL', 'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB', '1', '10', '1'],
        ['BNB', 'BEP20', null, '0.01', '0.02', '0.0005'],
        ['SOL', 'SOL', null, '0.01', '0.1', '0.005'],
        ['TRX', 'TRC20', null, '1', '10', '1'],
        ['USDC', 'ERC20', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', '10', '20', '5'],
        ['USDC', 'BEP20', '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', '1', '10', '0.3'],
        ['USDC', 'SOL', 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', '1', '10', '1'],
    ];

    public function run(): void
    {
        $sort = 0;
        foreach (self::ASSETS as $symbol => [$name, $precision, $display, $tolerance]) {
            Asset::query()->firstOrCreate(['symbol' => $symbol], [
                'name' => $name,
                'precision' => $precision,
                'display_precision' => $display,
                'icon_path' => 'images/assets/'.strtolower($symbol).'.svg',
                'is_fiat' => false,
                'is_active' => true,
                'sort' => $sort += 10,
                'reconcile_tolerance' => $tolerance,
            ]);
        }

        foreach (self::NETWORKS as $code => [$name, $txUrl, $addressUrl, $confirmations]) {
            Network::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'explorer_tx_url' => $txUrl,
                'explorer_address_url' => $addressUrl,
                'required_confirmations' => $confirmations,
                'is_active' => true,
            ]);
        }

        $assets = Asset::query()->pluck('id', 'symbol');
        $networks = Network::query()->pluck('id', 'code');

        foreach (self::ROUTES as [$symbol, $code, $contract, $minDeposit, $minWithdraw, $fee]) {
            AssetNetwork::query()->firstOrCreate(
                ['asset_id' => $assets[$symbol], 'network_id' => $networks[$code]],
                [
                    'contract_address' => $contract,
                    'deposit_enabled' => true,
                    'withdraw_enabled' => true,
                    'min_deposit' => $minDeposit,
                    'min_withdraw' => $minWithdraw,
                    'withdraw_fee' => $fee,
                    'memo_required' => false,
                ],
            );
        }
    }
}
