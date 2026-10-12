<x-layouts.guest :title="__('Account frozen')">
    <span class="mb-4 grid size-12 place-items-center rounded-2xl bg-up-soft text-up"><x-ui.icon name="check" :size="22" /></span>
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Your account is frozen') }}</h1>
    <p class="mt-2 text-[13.5px] text-fg-2">{{ __('Withdrawals and API keys are stopped and every device is signed out. Contact support from your account email so we can help you secure it.') }}</p>
</x-layouts.guest>
