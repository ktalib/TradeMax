<?php

declare(strict_types=1);

return [

    // internal = pure user order book; market_maker = platform MM account quotes around the index price.
    'liquidity_mode' => env('TRADING_LIQUIDITY_MODE', 'internal'),

    'default_fees' => [
        'maker_rate' => env('TRADING_MAKER_FEE', '0.001000'),
        'taker_rate' => env('TRADING_TAKER_FEE', '0.001000'),
    ],

    'limits' => [
        'orders_per_second_per_user' => 10,
        'market_order_max_slippage_pct' => '0.050000',
    ],

    'circuit_breaker' => [
        'move_pct_per_minute' => '0.100000',
        'halt_minutes' => 5,
    ],

    'book' => [
        'depth_broadcast_ms' => 250,
        'depth_levels' => 50,
    ],

    'copy' => [
        'default_max_slippage_pct' => '0.010000',
        'max_profit_share_rate' => '0.200000',
    ],

    'price_feed' => [
        'primary' => env('PRICE_FEED', 'binance'),
        'fallback' => env('PRICE_FEED_FALLBACK', 'coingecko'),
    ],

];
