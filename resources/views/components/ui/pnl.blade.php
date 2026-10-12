@props([
    'value',             // Decimal|numeric-string; a ratio when `percent` is set ("0.0231" → +2.31%)
    'percent' => false,
    'decimals' => 2,
    'suffix' => null,    // e.g. an asset symbol for absolute PnL
])

@php
    $d = \App\Support\Money\Decimal::of($value);
    $shown = $percent ? $d->mul(100) : $d;
    $zero = $shown->roundDown((int) $decimals)->isZero();
    $up = ! $shown->isNegative();
    $text = \App\Support\Money\DecimalFormatter::format($shown->abs(), (int) $decimals);
@endphp

{{-- Never colour alone: the arrow and sign carry the direction too. --}}
<span {{ $attributes->class(['num whitespace-nowrap', 'text-up' => $up && ! $zero, 'text-down' => ! $up && ! $zero, 'text-fg-2' => $zero]) }}>
    @unless ($zero)<span aria-hidden="true">{{ $up ? '▲' : '▼' }}</span>@endunless
    {{ $zero ? '' : ($up ? '+' : '−') }}{{ $text }}{{ $percent ? '%' : '' }}@if ($suffix) {{ $suffix }}@endif
</span>
