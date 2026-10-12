@props([
    'options' => [], // value => label
    'value' => null,
    'name' => null,  // when set, a hidden input carries the choice in a form
    'label' => null, // accessible group name
])

@php
    $value ??= array_key_first($options);
@endphp

<div {{ $attributes->class('seg') }} role="group" @if ($label) aria-label="{{ $label }}" @endif x-data="{ value: @js((string) $value) }" x-modelable="value">
    @foreach ($options as $key => $text)
        <button type="button" @click="value = @js((string) $key); $dispatch('change', value)" :aria-pressed="(value === @js((string) $key)).toString()" aria-pressed="{{ (string) $key === (string) $value ? 'true' : 'false' }}">{{ $text }}</button>
    @endforeach
    @if ($name)<input type="hidden" name="{{ $name }}" :value="value">@endif
</div>
