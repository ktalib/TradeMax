<x-layouts.guest :title="__('Account frozen')">
    <span class="mb-4 grid size-12 place-items-center rounded-2xl bg-down-soft text-down"><x-ui.icon name="shield" :size="22" /></span>
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Your account is frozen') }}</h1>
    <p class="mt-2 text-[13.5px] text-fg-2">{{ __('Withdrawals and API keys are stopped and other devices were signed out. Your funds stay where they are.') }}</p>
    @if ($frozenAt)
        <p class="mt-2 text-[13px] text-fg-3">{{ __('Frozen on :date (UTC).', ['date' => $frozenAt->format('Y-m-d H:i')]) }}</p>
    @endif

    <div class="mt-5 rounded-[var(--radius)] bg-surface-2 p-4 text-[13px] text-fg-2">
        <p class="font-semibold text-fg">{{ __('What happens next') }}</p>
        <p class="mt-1">{{ __('Contact support from the email address on your account. To unfreeze, we check your identity and review recent activity with you first.') }}</p>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <x-ui.button type="submit" variant="secondary" block>{{ __('Sign out') }}</x-ui.button>
    </form>
</x-layouts.guest>
