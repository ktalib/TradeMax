@props([
    'tone' => 'neutral', // success | danger | warning | info | brand | accent | neutral
    'dot' => false,
])

<span {{ $attributes->class(['badge', 'tone-'.$tone, 'badge-dot' => $dot]) }}>{{ $slot }}</span>
