@props([
    'name',
    'label' => null,
    'id' => null,
    'prefix' => null,
    'suffix' => null,
    'max' => null,      // value to fill when the Max button is pressed; null hides the button
    'hint' => null,
    'type' => 'text',
])

@php
    $id ??= 'f-'.str_replace(['.', '[', ']'], '-', $name);
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp

<div {{ $attributes->only('class') }} @if ($max !== null) x-data="{ v: '' }" @endif>
    @if ($label)
        <label class="field-label" for="{{ $id }}">{{ $label }}</label>
    @endif
    <div @class(['field', 'invalid' => $error])>
        @if ($prefix)<span class="field-affix" style="color:var(--text-3);font-weight:500">{{ $prefix }}</span>@endif
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $attributes->get('value')) }}"
            @if ($max !== null) x-ref="input" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'value']) }}
        >
        @if ($suffix)<span class="field-affix">{{ $suffix }}</span>@endif
        @if ($max !== null)
            <button type="button" class="field-max" @click="$refs.input.value = @js((string) $max); $refs.input.dispatchEvent(new Event('input', { bubbles: true }))">{{ __('Max') }}</button>
        @endif
    </div>
    @if ($hint)<p class="field-hint" id="{{ $id }}-hint">{{ $hint }}</p>@endif
    @if ($error)<p class="field-error" id="{{ $id }}-error">{{ $error }}</p>@endif
</div>
