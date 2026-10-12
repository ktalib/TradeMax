@props(['title' => null, 'subtitle' => null, 'padded' => true])

<section {{ $attributes->class('card') }}>
    @if ($title || isset($actions))
        <header class="card-h">
            <div>
                @if ($title)<h2 class="text-[15px] font-semibold">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="text-xs text-fg-3">{{ $subtitle }}</p>@endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif
    <div @class(['card-b' => $padded, 'pt-5' => $padded && ! $title && ! isset($actions)])>
        {{ $slot }}
    </div>
</section>
