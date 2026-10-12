@props([
    'name',          // open with: $dispatch('open-modal', 'name'); close with 'close-modal'
    'title',
    'sheet' => true, // bottom sheet on phones
])

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    {{ $attributes }}
>
    <div x-show="open" x-cloak x-transition.opacity @class(['overlay', 'sheet' => $sheet]) @click.self="open = false">
        <div class="modal" role="dialog" aria-modal="true" aria-label="{{ $title }}" x-trap.noscroll="open">
            <div class="card-h" style="padding:22px 24px 6px">
                <h2 class="font-display text-lg font-semibold">{{ $title }}</h2>
                <button type="button" class="icon-btn" @click="open = false" aria-label="{{ __('Close') }}"><x-ui.icon name="close" /></button>
            </div>
            <div class="flex flex-col gap-4" style="padding:16px 24px 24px">
                {{ $slot }}
                @isset($footer)
                    <div class="flex gap-2">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>
</div>
