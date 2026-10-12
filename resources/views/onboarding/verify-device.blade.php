<x-layouts.guest :title="__('Confirm this device')">
    <span class="mb-4 grid size-12 place-items-center rounded-2xl bg-warning-soft text-warning"><x-ui.icon name="shield" :size="22" /></span>
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Confirm it\'s you') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('This is a new device or network for your account. We will email a 6-digit code to :email.', ['email' => $maskedEmail]) }}</p>

    @include('auth.partials.status')

    <form method="POST" action="{{ route('device.verify.send') }}" class="mb-5">
        @csrf
        <x-ui.button type="submit" variant="secondary" block>{{ session('status') ? __('Send a new code') : __('Email me a code') }}</x-ui.button>
    </form>

    <form method="POST" action="{{ route('device.verify.confirm') }}" class="flex flex-col gap-4">
        @csrf
        <x-ui.input name="code" :label="__('Code from the email')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
        <label class="flex items-start gap-2.5 text-[13px] text-fg-2">
            <input type="checkbox" name="remember" value="1" class="mt-1 accent-[var(--brand)]">
            <span>{{ __('Trust this device for 30 days. Only tick this on a device you own and keep locked.') }}</span>
        </label>
        <x-ui.button type="submit" size="lg" block>{{ __('Confirm device') }}</x-ui.button>
    </form>

    <x-slot:below>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="font-semibold text-brand hover:underline">{{ __('Not you? Sign out') }}</button></form>
    </x-slot:below>
</x-layouts.guest>
