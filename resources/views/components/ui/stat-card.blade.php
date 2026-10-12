@props([
    'label',
    'value',               // already formatted for display, e.g. from the amount component
    'delta' => null,       // Decimal|numeric-string ratio, e.g. "0.0231" for +2.31%
    'deltaLabel' => null,  // e.g. "24h"
    'sparkline' => [],     // list of numeric strings, oldest first
])

@php
    // Geometry is computed with Decimal too: the house rule is no floats anywhere near prices.
    $w = 120;
    $h = 40;
    $points = '';
    $up = true;
    if (count($sparkline) > 1) {
        $values = array_map(fn ($v) => \App\Support\Money\Decimal::of($v), $sparkline);
        $min = \App\Support\Money\Decimal::min(...$values);
        $max = \App\Support\Money\Decimal::max(...$values);
        $range = $max->sub($min);
        $last = count($values) - 1;
        $coords = [];
        foreach ($values as $i => $v) {
            $x = \App\Support\Money\Decimal::of($i * $w)->div($last);
            $y = $range->isZero()
                ? \App\Support\Money\Decimal::of($h)->div(2)
                : \App\Support\Money\Decimal::of($h - 3)->sub($v->sub($min)->div($range)->mul($h - 6));
            $coords[] = $x->toFixed(1).','.$y->toFixed(1);
        }
        $points = implode(' ', $coords);
        $up = $values[$last]->gte($values[0]);
    }
@endphp

<div {{ $attributes->class('card') }} style="padding:18px 20px">
    <div class="eyebrow">{{ $label }}</div>
    <div class="mt-1 flex items-end justify-between gap-3">
        <div class="min-w-0">
            <div class="num font-display text-2xl font-semibold tracking-tight">{{ $value }}</div>
            @if ($delta !== null)
                <div class="mt-1 text-[13px]">
                    <x-ui.pnl :value="$delta" percent />
                    @if ($deltaLabel)<span class="text-fg-3">{{ $deltaLabel }}</span>@endif
                </div>
            @endif
        </div>
        @if ($points !== '')
            <svg width="{{ $w }}" height="{{ $h }}" viewBox="0 0 {{ $w }} {{ $h }}" aria-hidden="true" style="flex:none">
                <polyline points="{{ $points }}" fill="none" stroke="var(--{{ $up ? 'up' : 'down' }})" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" />
            </svg>
        @endif
    </div>
</div>
