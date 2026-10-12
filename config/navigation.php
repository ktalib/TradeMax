<?php

declare(strict_types=1);

/*
| Sidebar navigation. Labels are translation keys (passed through __()). An item whose route
| doesn't exist yet renders disabled, so the shell can ship before every module does.
*/

return [
    [
        'label' => 'Trade',
        'items' => [
            ['label' => 'Overview', 'route' => 'dashboard', 'icon' => 'home'],
            ['label' => 'Markets', 'route' => 'markets.index', 'icon' => 'markets'],
            ['label' => 'Spot trading', 'route' => 'trade.show', 'icon' => 'trade'],
            ['label' => 'Signals', 'route' => 'signals.index', 'icon' => 'signals'],
        ],
    ],
    [
        'label' => 'Invest',
        'items' => [
            ['label' => 'Earn', 'route' => 'earn.index', 'icon' => 'earn'],
            ['label' => 'Copy trading', 'route' => 'copy.index', 'icon' => 'copy-trading'],
        ],
    ],
    [
        'label' => 'Account',
        'items' => [
            ['label' => 'Wallet', 'route' => 'wallet.index', 'icon' => 'wallet'],
            ['label' => 'Security', 'route' => 'security.index', 'icon' => 'shield'],
        ],
    ],
];
