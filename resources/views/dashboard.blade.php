<x-layouts.app :title="__('Overview')">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">{{ __('Welcome, :name', ['name' => \Illuminate\Support\Str::of($user->name)->before(' ')]) }}</h1>
            <p class="mt-1.5 text-fg-2">{{ __('Your account is set up and protected. Wallets and trading arrive in the next releases.') }}</p>
        </div>
    </div>

    @if ($user->withdrawalsLocked())
        <div class="flex flex-wrap items-center gap-3 rounded-[var(--radius-lg)] border border-[color-mix(in_srgb,var(--warning)_35%,transparent)] bg-warning-soft px-5 py-4 text-[13.5px]" role="status">
            <x-ui.icon name="alert" class="text-warning" />
            <span class="flex-1">{{ __('Withdrawals are paused after a recent security change. They resume in') }} <x-ui.countdown :until="$user->withdrawal_locked_until" class="font-semibold" />.</span>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <x-ui.card :title="__('Account checklist')" :subtitle="__('Verification level :tier', ['tier' => $user->kyc_tier])">
            <x-ui.stepper :current="4" :steps="[
                ['label' => __('Email confirmed')],
                ['label' => __('Phone number confirmed')],
                ['label' => __('Two-step verification on')],
                ['label' => __('Verify your identity'), 'hint' => __('ID document and selfie. Unlocks deposits, trading and higher limits.')],
            ]" />
            <p class="mt-4 text-[13px] text-fg-3">{{ __('Identity verification opens in an upcoming release.') }}</p>
        </x-ui.card>

        <x-ui.card :title="__('Security')">
            <x-slot:actions><x-ui.button :href="route('security.index')" size="sm" variant="ghost">{{ __('Manage') }}</x-ui.button></x-slot:actions>
            <dl class="flex flex-col gap-3 text-[13.5px]">
                <div class="flex justify-between gap-3"><dt class="text-fg-2">{{ __('Two-step verification') }}</dt><dd><x-ui.badge tone="success" dot>{{ __('On') }}</x-ui.badge></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-2">{{ __('Anti-phishing code') }}</dt><dd>@if ($user->anti_phishing_code)<x-ui.badge tone="success" dot>{{ __('Set') }}</x-ui.badge>@else<a href="{{ route('security.index') }}" class="font-semibold text-brand hover:underline">{{ __('Set one') }}</a>@endif</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-2">{{ __('Previous sign-in') }}</dt><dd class="text-end">{{ $previousLogin ? $previousLogin->created_at->setTimezone($user->timezone)->isoFormat('lll') : __('This is your first') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-fg-2">{{ __('Referral code') }}</dt><dd class="font-mono">{{ $user->referral_code }}</dd></div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.app>
