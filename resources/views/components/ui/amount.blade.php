@props([
    'value',            // Decimal|numeric-string
    'asset' => null,    // symbol shown after the number
    'decimals' => 2,    // asset display precision
    'trim' => false,    // drop trailing zeros (keeps at least 2)
])

<span {{ $attributes->class('num') }}>{{ \App\Support\Money\DecimalFormatter::format(\App\Support\Money\Decimal::of($value), (int) $decimals, trimZeros: (bool) $trim) }}@if ($asset)<span class="text-fg-2"> {{ $asset }}</span>@endif</span>
