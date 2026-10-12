@props(['title' => null, 'wide' => false, 'captcha' => null])

@php
    $locale = app()->getLocale();
    $rtl = in_array($locale, config('platform.rtl_locales', []), true);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <script nonce="{{ Vite::cspNonce() }}">
        try { const t = localStorage.getItem('tm-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if ($captcha)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>
    @endif
</head>
<body>
    @if (config('platform.demo'))
        <div class="demo-banner" role="status">{{ __('Demo environment · sample data · no real funds') }}</div>
    @endif

    <main class="flex min-h-[calc(100vh-30px)] flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="logo mb-8">
            <span class="logo-mark"><x-ui.icon name="markets" :size="16" stroke-width="2.6" /></span>
            <span>{{ config('app.name') }}</span>
        </a>

        <div @class(['card w-full', 'max-w-[440px]' => ! $wide, 'max-w-[560px]' => $wide]) style="padding:28px">
            {{ $slot }}
        </div>

        @isset($below)
            <div class="mt-6 text-center text-[13px] text-fg-2">{{ $below }}</div>
        @endisset

        <p class="mt-10 max-w-[52ch] text-center text-xs text-fg-3">{{ __('We will never ask for your password or codes by phone, chat or email.') }}</p>
    </main>

    <x-ui.toast />
    @livewireScriptConfig(['nonce' => Vite::cspNonce()])
</body>
</html>
