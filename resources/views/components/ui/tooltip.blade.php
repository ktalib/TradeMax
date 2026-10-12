@props(['text'])

@php($id = 'tip-'.\Illuminate\Support\Str::random(6))

<span {{ $attributes->class('tooltip') }} aria-describedby="{{ $id }}">
    {{ $slot }}
    <span class="tooltip-bubble" role="tooltip" id="{{ $id }}">{{ $text }}</span>
</span>
