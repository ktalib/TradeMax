@props(['name'])

<div role="tabpanel" x-show="tab === @js((string) $name)" x-cloak {{ $attributes }}>
    {{ $slot }}
</div>
