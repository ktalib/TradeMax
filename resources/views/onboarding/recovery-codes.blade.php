<x-layouts.guest :title="__('Save your recovery codes')" wide>
    <span class="mb-4 grid size-12 place-items-center rounded-2xl bg-up-soft text-up"><x-ui.icon name="check" :size="22" /></span>
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Two-step verification is on') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('Save these recovery codes somewhere safe and offline. Each one signs you in once if you lose your phone. This is the only time we show them.') }}</p>

    <div class="grid grid-cols-2 gap-2 rounded-[var(--radius)] border border-border bg-surface-2 p-4 font-mono text-[14px]" dir="ltr">
        @foreach ($codes as $code)
            <span>{{ $code }}</span>
        @endforeach
    </div>

    <div class="mt-4" x-data="copyText(@js(implode("\n", $codes)), @js(__('Recovery codes copied')))">
        <span x-ref="text" class="sr-only">{{ implode(' ', $codes) }}</span>
        <x-ui.button variant="secondary" block @click="copy()">{{ __('Copy codes') }}</x-ui.button>
    </div>

    <x-ui.button :href="route('dashboard')" size="lg" block class="mt-3">{{ __('I saved them, continue') }}</x-ui.button>
</x-layouts.guest>
