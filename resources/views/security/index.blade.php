<x-layouts.app :title="__('Account & security')">
    <div>
        <h1 class="font-display text-3xl font-semibold tracking-tight">{{ __('Account & security') }}</h1>
        <p class="mt-1.5 max-w-[64ch] text-fg-2">{{ __('Changes here need your authenticator code and a code we email you. Changing your password, email, phone or authenticator pauses withdrawals for 24 hours.') }}</p>
    </div>

    @if (session('status'))
        <div class="rounded-[var(--radius)] bg-up-soft px-4 py-3 text-[13.5px] text-up" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-[var(--radius)] bg-down-soft px-4 py-3 text-[13.5px] text-down" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-2">
        <div class="flex flex-col gap-5">
            <livewire:security.two-factor-settings />
            <livewire:security.password-settings />
            <livewire:security.contact-settings />
        </div>
        <div class="flex flex-col gap-5">
            <livewire:security.anti-phishing-settings />
            <livewire:security.api-keys />
            <livewire:security.freeze-account-card />
        </div>
    </div>

    <livewire:security.devices-and-sessions />
</x-layouts.app>
