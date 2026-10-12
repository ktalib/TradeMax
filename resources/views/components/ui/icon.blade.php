@props(['name', 'size' => 20])

@php
    $paths = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/>',
        'markets' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-6"/>',
        'trade' => '<path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="3"/><path d="M16 13h2M3 10h18"/>',
        'earn' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.6-1.5-3-1.5-1.7 0-3 .8-3 2s1.3 1.7 3 2 3 .8 3 2-1.3 2-3 2c-1.4 0-2.5-.5-3-1.5M12 6.5V8M12 16v1.5"/>',
        'copy-trading' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 3-5.5 6.5-5.5s6 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.8c1.8.8 2.8 2.6 3 5.2"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'chevrons-left' => '<path d="m11 17-5-5 5-5M18 17l-5-5 5-5"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'check' => '<path d="m5 12 5 5L20 7"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'arrow-down' => '<path d="M12 5v14M5 12l7 7 7-7"/>',
        'arrow-up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>',
        'alert' => '<path d="M12 3 2 21h20L12 3Z"/><path d="M12 10v4M12 17h.01"/>',
        'inbox' => '<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5 5h14l2 8v6H3v-6l2-8Z"/>',
    ];
@endphp

<svg {{ $attributes->merge(['width' => $size, 'height' => $size]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
