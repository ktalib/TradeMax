<x-layouts.guest :title="__('Choose a new password')">
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Choose a new password') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('Every signed-in device will be signed out, and withdrawals pause for 24 hours.') }}</p>

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-ui.input name="email" type="email" :label="__('Email')" :value="$email" autocomplete="username" required />
        <x-ui.input name="password" type="password" :label="__('New password')" autocomplete="new-password" :hint="__('At least 10 characters. A phrase of unrelated words works well.')" required />
        <x-ui.input name="password_confirmation" type="password" :label="__('Confirm new password')" autocomplete="new-password" required />
        <x-ui.button type="submit" size="lg" block>{{ __('Save new password') }}</x-ui.button>
    </form>
</x-layouts.guest>
