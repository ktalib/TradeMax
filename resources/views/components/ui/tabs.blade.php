@props([
    'tabs' => [],   // key => label
    'active' => null,
])

@php
    $active ??= array_key_first($tabs);
    $uid = 'tabs-'.\Illuminate\Support\Str::random(6);
@endphp

{{-- Panels are <x-ui.tab-panel name="key"> children; they read `tab` from this scope. --}}
<div {{ $attributes }} x-data="{ tab: @js((string) $active) }">
    <div class="tabs" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" id="{{ $uid }}-{{ $key }}" aria-controls="{{ $uid }}-{{ $key }}-panel"
                @click="tab = @js((string) $key)"
                :aria-selected="(tab === @js((string) $key)).toString()" aria-selected="{{ (string) $key === (string) $active ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div data-tabs-id="{{ $uid }}">
        {{ $slot }}
    </div>
</div>
