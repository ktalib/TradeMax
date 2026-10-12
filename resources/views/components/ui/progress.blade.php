@props([
    'value',            // Decimal|numeric-string ratio between 0 and 1
    'label' => null,
    'tone' => 'accent', // accent | brand | up | down | warning
])

@php
    $pct = \App\Support\Money\Decimal::max(0, \App\Support\Money\Decimal::min(100, \App\Support\Money\Decimal::of($value)->mul(100)));
@endphp

<div {{ $attributes }}>
    @if ($label)
        <div class="mb-1.5 flex justify-between text-xs text-fg-3"><span>{{ $label }}</span><span class="num">{{ $pct->roundHalfUp(0)->toString() }}%</span></div>
    @endif
    <div class="h-1.5 overflow-hidden rounded-full bg-surface-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct->roundHalfUp(0)->toString() }}" @if ($label) aria-label="{{ $label }}" @endif>
        <div class="h-full rounded-full" style="width: {{ $pct->toFixed(2) }}%; background: var(--{{ $tone }})"></div>
    </div>
</div>
