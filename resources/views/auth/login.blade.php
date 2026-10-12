<x-layouts.guest :title="__('Sign in')" :captcha="$showCaptcha && $captchaSiteKey">
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Sign in') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('Check the address bar shows our real domain before you type your password.') }}</p>

    @include('auth.partials.status')

    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('Email')" autocomplete="username" required autofocus />
        <x-ui.input name="password" type="password" :label="__('Password')" autocomplete="current-password" required />

        <div class="flex items-center justify-between text-[13px]">
            <label class="flex items-center gap-2 text-fg-2"><input type="checkbox" name="remember" value="1" class="accent-[var(--brand)]"> {{ __('Keep me signed in') }}</label>
            <a href="{{ route('password.request') }}" class="font-semibold text-brand hover:underline">{{ __('Forgot password?') }}</a>
        </div>

        @if ($showCaptcha && $captchaSiteKey)
            <div class="cf-turnstile" data-sitekey="{{ $captchaSiteKey }}" data-theme="auto"></div>
        @endif

        <x-ui.button type="submit" size="lg" block>{{ __('Sign in') }}</x-ui.button>
    </form>

    <x-slot:below>
        {{ __('New here?') }} <a href="{{ route('register') }}" class="font-semibold text-brand hover:underline">{{ __('Create an account') }}</a>
    </x-slot:below>
</x-layouts.guest>
