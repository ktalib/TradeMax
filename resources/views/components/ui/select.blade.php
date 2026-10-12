@props([
    'name',
    'label' => null,
    'id' => null,
    'options' => [], // value => label
    'selected' => null,
    'placeholder' => null,
])

@php
    $id ??= 'f-'.str_replace(['.', '[', ']'], '-', $name);
    $error = $errors->first($name);
    $current = (string) old($name, $selected);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label class="field-label" for="{{ $id }}">{{ $label }}</label>
    @endif
    <div @class(['field', 'invalid' => $error]) style="padding-inline-end:12px">
        <select id="{{ $id }}" name="{{ $name }}" @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif {{ $attributes->except('class') }}>
            @if ($placeholder)<option value="" disabled @selected($current === '')>{{ $placeholder }}</option>@endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected($current === (string) $value)>{{ $text }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-ui.icon name="arrow-down" :size="14" style="color:var(--text-3);pointer-events:none" />
    </div>
    @if ($error)<p class="field-error" id="{{ $id }}-error">{{ $error }}</p>@endif
</div>
