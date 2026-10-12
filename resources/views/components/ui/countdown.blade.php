@props([
    'until',                 // CarbonInterface|string (ISO 8601, UTC)
    'doneLabel' => null,
])

@php
    $iso = $until instanceof \Carbon\CarbonInterface ? $until->toIso8601String() : (string) $until;
@endphp

<span {{ $attributes->class('num font-mono text-[13px]') }} x-data="countdown(@js($iso))">
    <span x-show="!done">
        <span x-show="parts.d > 0"><span x-text="parts.d"></span>{{ __('d') }} </span><span x-text="pad(parts.h)"></span>:<span x-text="pad(parts.m)"></span>:<span x-text="pad(parts.s)"></span>
    </span>
    <span x-show="done" x-cloak>{{ $doneLabel ?? __('Done') }}</span>
</span>
