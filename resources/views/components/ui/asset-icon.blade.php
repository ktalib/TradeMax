@props([
    'symbol',
    'size' => 34,
    'src' => null, // icon_path from the assets table once it exists
])

@php
    $known = ['BTC', 'ETH', 'USDT', 'USDC', 'SOL', 'BNB', 'TRX'];
    $upper = strtoupper($symbol);
    $colour = in_array($upper, $known, true) ? 'var(--asset-'.strtolower($upper).')' : 'var(--brand)';
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $upper }}" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class('rounded-full flex-none') }}>
@else
    <span {{ $attributes->class('coin') }} style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(8, intdiv((int) $size, 3)) }}px;background:{{ $colour }}" role="img" aria-label="{{ $upper }}">{{ mb_substr($upper, 0, 2) }}</span>
@endif
