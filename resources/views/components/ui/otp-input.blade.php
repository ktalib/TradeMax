@props([
    'name',
    'label',
    'length' => 6,
])

@php
    $id = 'otp-'.str_replace(['.', '[', ']'], '-', $name);
    $error = $errors->first($name);
@endphp

<div {{ $attributes }} x-data="otpInput({{ (int) $length }})">
    <span class="field-label" id="{{ $id }}-label">{{ $label }}</span>
    <div class="otp flex gap-2" role="group" aria-labelledby="{{ $id }}-label" dir="ltr">
        @for ($i = 0; $i < $length; $i++)
            <input
                x-ref="d{{ $i }}"
                inputmode="numeric"
                autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                maxlength="1"
                aria-label="{{ __('Digit :n of :total', ['n' => $i + 1, 'total' => $length]) }}"
                @input="input({{ $i }}, $event)"
                @keydown.backspace="back({{ $i }}, $event)"
                @paste="paste($event)"
            >
        @endfor
    </div>
    <input type="hidden" name="{{ $name }}" :value="value">
    @if ($error)<p class="field-error">{{ $error }}</p>@endif
</div>
