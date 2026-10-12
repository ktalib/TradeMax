<x-layouts.guest :title="__('Confirm your phone')">
    @include('onboarding.partials.steps', ['current' => 2])

    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Confirm your phone number') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('We will text a 6-digit code to :phone.', ['phone' => $maskedPhone]) }}</p>

    @include('auth.partials.status')

    <form method="POST" action="{{ route('onboarding.phone.send') }}" class="mb-5">
        @csrf
        <x-ui.button type="submit" variant="secondary" block>{{ session('status') ? __('Send a new code') : __('Text me a code') }}</x-ui.button>
        @error('code')@if (str_contains($message, 'Too many'))<p class="field-error">{{ $message }}</p>@endif @enderror
    </form>

    <form method="POST" action="{{ route('onboarding.phone.verify') }}" class="flex flex-col gap-4">
        @csrf
        <x-ui.input name="code" :label="__('Code from the SMS')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
        <x-ui.button type="submit" size="lg" block>{{ __('Confirm phone') }}</x-ui.button>
    </form>

    <details class="mt-6 text-[13px]" @if ($errors->has('phone')) open @endif>
        <summary class="cursor-pointer font-semibold text-brand">{{ __('Wrong number?') }}</summary>
        <form method="POST" action="{{ route('onboarding.phone.update') }}" class="mt-4 flex flex-col gap-4">
            @csrf
            <x-ui.select name="phone_country" :label="__('Country')" :options="$countries" :selected="$residence" />
            <x-ui.input name="phone" type="tel" :label="__('Mobile number')" inputmode="tel" autocomplete="tel-national" required />
            <x-ui.button type="submit" variant="secondary" block>{{ __('Update number') }}</x-ui.button>
        </form>
    </details>
</x-layouts.guest>
