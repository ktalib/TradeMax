@props([
    'value',
    'label' => null,
    'copy' => true,
])

{{-- First and last 6 characters are emphasised to help users spot address-poisoning lookalikes. --}}
@php
    $head = mb_substr($value, 0, 6);
    $tail = mb_strlen($value) > 12 ? mb_substr($value, -6) : '';
    $middle = mb_strlen($value) > 12 ? mb_substr($value, 6, mb_strlen($value) - 12) : mb_substr($value, 6);
@endphp

<div {{ $attributes->class('flex flex-col gap-1.5') }} @if ($copy) x-data="copyText(@js($value), @js(__('Address copied')))" @endif>
    @if ($label)<span class="field-label" style="margin:0">{{ $label }}</span>@endif
    <div class="flex items-center gap-2.5 rounded-[var(--radius)] border border-border bg-surface-2 py-3 ps-3.5 pe-3">
        <code x-ref="text" class="min-w-0 flex-1 font-mono text-[13px] break-all text-fg-2" dir="ltr"><span class="font-medium text-fg">{{ $head }}</span>{{ $middle }}<span class="font-medium text-fg">{{ $tail }}</span></code>
        @if ($copy)
            <x-ui.button variant="secondary" size="sm" @click="copy()">
                <span x-show="!copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
            </x-ui.button>
        @endif
    </div>
</div>
