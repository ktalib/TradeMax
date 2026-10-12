@props(['title' => null, 'wide' => false])

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
    <script @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>
        try { const t = localStorage.getItem('tm-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    @if (config('platform.demo'))
        <div class="demo-banner" role="status">{{ __('Demo environment · sample data · no real funds') }}</div>
    @endif

    <div class="shell" x-data="shell" :class="{ collapsed, drawer }">
        <aside class="side" aria-label="{{ __('Main navigation') }}">
            <div class="side-top">
                <a class="logo" href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}">
                    <span class="logo-mark"><x-ui.icon name="markets" :size="16" stroke-width="2.6" /></span>
                    <span class="logo-txt">{{ config('app.name') }}</span>
                </a>
                <button type="button" class="icon-btn collapse-btn" style="width:32px;height:32px" @click="toggleCollapsed()" :aria-label="collapsed ? @js(__('Expand sidebar')) : @js(__('Collapse sidebar'))">
                    <x-ui.icon name="chevrons-left" :size="17" class="collapse-icon" style="transition:transform .2s ease-out" />
                </button>
            </div>

            <nav>
                @foreach (config('navigation') as $group)
                    <div class="navg">
                        <div class="navl">{{ __($group['label']) }}</div>
                        @foreach ($group['items'] as $item)
                            @if (Route::has($item['route']))
                                <a class="nav" href="{{ route($item['route']) }}" title="{{ __($item['label']) }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                                    <x-ui.icon :name="$item['icon']" /><span>{{ __($item['label']) }}</span>
                                </a>
                            @else
                                <span class="nav" aria-disabled="true" title="{{ __($item['label']) }}">
                                    <x-ui.icon :name="$item['icon']" /><span>{{ __($item['label']) }}</span><em>{{ __('Soon') }}</em>
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </nav>

            @isset($sidebarFooter)
                <div style="margin-top:auto;padding-top:24px">{{ $sidebarFooter }}</div>
            @endisset
        </aside>
        <div class="scrim" @click="drawer = false"></div>

        <div class="shell-main">
            <header class="hdr">
                <button type="button" class="icon-btn menu-btn" @click="drawer = true" aria-label="{{ __('Open menu') }}"><x-ui.icon name="menu" /></button>
                <div style="flex:1;min-width:0">{{ $header ?? '' }}</div>
                <div x-data="themeSwitcher">
                    <button type="button" class="icon-btn" @click="toggle()" aria-label="{{ __('Switch between light and dark theme') }}"><x-ui.icon name="sun" /></button>
                </div>
                {{ $actions ?? '' }}
            </header>

            <main @class(['page', 'wide' => $wide])>
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.toast />

    @livewireScriptConfig
</body>
</html>
