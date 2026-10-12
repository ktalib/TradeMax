<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use Illuminate\Contracts\View\View;

/**
 * Renders every UI component with sample data. Registered only in local and testing
 * environments (routes/web.php), so it never ships to production.
 */
final class ComponentGalleryController
{
    public function __invoke(): View
    {
        return view('dev.ui', [
            'balances' => [
                ['symbol' => 'BTC', 'name' => 'Bitcoin', 'total' => '0.43150000', 'decimals' => 8, 'change' => '0.0231'],
                ['symbol' => 'ETH', 'name' => 'Ethereum', 'total' => '3.820000', 'decimals' => 6, 'change' => '-0.0118'],
                ['symbol' => 'USDT', 'name' => 'Tether', 'total' => '13980.55', 'decimals' => 2, 'change' => '0'],
                ['symbol' => 'SOL', 'name' => 'Solana', 'total' => '41.2000', 'decimals' => 4, 'change' => '0.0542'],
            ],
            'sparkline' => ['64210.5', '64880.1', '64402.9', '65120', '65990.4', '65712.2', '66840.7', '67420.5'],
            'steps' => [
                ['label' => 'Email confirmed'],
                ['label' => 'Phone and profile', 'hint' => 'Tier 1 · withdraw up to $1,000/day'],
                ['label' => 'ID document and selfie', 'hint' => 'Unlocks $10,000/day'],
                ['label' => 'Proof of address', 'hint' => 'Unlocks $50,000/day'],
            ],
            'maturesAt' => now()->addDays(12)->addHours(5),
        ]);
    }
}
