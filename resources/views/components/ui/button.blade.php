@props([
    'variant' => 'primary', // primary | secondary | ghost | danger | buy | sell
    'size' => 'md',         // sm | md | lg
    'href' => null,
    'type' => 'button',
    'loading' => false,
    'block' => false,
])

@php
    $classes = ['btn', 'btn-'.$variant, 'btn-sm' => $size === 'sm', 'btn-lg' => $size === 'lg', 'w-full' => $block];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }} @if ($loading) aria-disabled="true" aria-busy="true" @endif>
        @if ($loading)<span class="spinner" aria-hidden="true"></span>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @disabled($loading) @if ($loading) aria-busy="true" @endif>
        @if ($loading)<span class="spinner" aria-hidden="true"></span>@endif
        {{ $slot }}
    </button>
@endif
