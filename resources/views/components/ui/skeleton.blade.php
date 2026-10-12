@props([
    'lines' => 1,
    'width' => '100%',
    'height' => '14px',
])

<div {{ $attributes->class('flex flex-col gap-2') }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <div class="skeleton" style="height: {{ $height }}; width: {{ $lines > 1 && $i === $lines - 1 ? '60%' : $width }}"></div>
    @endfor
</div>
