@props([
    'stack' => true, // under 640px rows become label/value cards; give each <td> a data-label
])

<div class="tbl-wrap">
    <table {{ $attributes->class(['tbl', 'stack' => $stack]) }}>
        @isset($head)
            <thead><tr>{{ $head }}</tr></thead>
        @endisset
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
