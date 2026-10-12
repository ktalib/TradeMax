<x-layouts.guest :title="__('Freeze account')">
    @if ($alreadyFrozen)
        <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('This account is already frozen') }}</h1>
        <p class="mt-2 text-[13.5px] text-fg-2">{{ __('Withdrawals and API keys are stopped. Contact support to continue.') }}</p>
    @else
        <span class="mb-4 grid size-12 place-items-center rounded-2xl bg-down-soft text-down"><x-ui.icon name="alert" :size="22" /></span>
        <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Freeze your account?') }}</h1>
        <p class="mt-2 text-[13.5px] text-fg-2">{{ __('Withdrawals and API keys stop immediately and every device is signed out. Your funds stay safe in your account. Unfreezing needs a check by support.') }}</p>
        <form method="POST" action="{{ $action }}" class="mt-6">
            @csrf
            <x-ui.button type="submit" variant="danger" size="lg" block>{{ __('Freeze my account now') }}</x-ui.button>
        </form>
    @endif
</x-layouts.guest>
