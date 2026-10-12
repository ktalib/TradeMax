@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center gap-3 px-6 py-12 text-center') }}>
    <span class="grid size-12 place-items-center rounded-2xl bg-surface-2 text-fg-3"><x-ui.icon :name="$icon" :size="22" /></span>
    <div>
        <h3 class="text-[15px] font-semibold">{{ $title }}</h3>
        @if ($description)<p class="mx-auto mt-1 max-w-[46ch] text-[13px] text-fg-2">{{ $description }}</p>@endif
    </div>
    @isset($action)
        <div class="mt-1">{{ $action }}</div>
    @endisset
</div>
