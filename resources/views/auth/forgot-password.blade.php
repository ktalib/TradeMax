<x-layouts.guest :title="__('Reset your password')">
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Reset your password') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('Enter your account email and we will send you a reset link. After a reset, withdrawals pause for 24 hours.') }}</p>

    @include('auth.partials.status')

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('Email')" autocomplete="username" required autofocus />
        <x-ui.button type="submit" size="lg" block>{{ __('Send reset link') }}</x-ui.button>
    </form>

    <x-slot:below>
        <a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">{{ __('Back to sign in') }}</a>
    </x-slot:below>
</x-layouts.guest>
