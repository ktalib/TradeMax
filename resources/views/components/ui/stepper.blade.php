@props([
    'steps' => [],   // list of ['label' => string, 'hint' => ?string]
    'current' => 1,  // 1-based; earlier steps show as done
])

<ol {{ $attributes->class('flex flex-col') }}>
    @foreach ($steps as $i => $step)
        @php
            $n = $i + 1;
            $state = $n < $current ? 'done' : ($n === (int) $current ? 'now' : 'todo');
        @endphp
        <li class="relative flex gap-3 pb-4 last:pb-0" @if ($state === 'now') aria-current="step" @endif>
            @unless ($loop->last)
                <span class="absolute start-[10px] top-[24px] bottom-1 w-px bg-border-strong" aria-hidden="true"></span>
            @endunless
            <span @class([
                'relative grid size-[21px] flex-none place-items-center rounded-full border-[1.5px] text-[10.5px] font-bold',
                'border-up bg-up text-white' => $state === 'done',
                'border-brand bg-surface text-brand shadow-[0_0_0_4px_var(--brand-soft)]' => $state === 'now',
                'border-border-strong bg-surface text-fg-3' => $state === 'todo',
            ])>
                @if ($state === 'done')<x-ui.icon name="check" :size="12" stroke-width="3" />@else{{ $n }}@endif
            </span>
            <div class="text-[13px]">
                <div @class(['font-medium', 'text-fg' => $state !== 'todo', 'text-fg-2' => $state === 'todo'])>
                    {{ $step['label'] }}
                    @if ($state === 'done')<span class="sr-only">({{ __('completed') }})</span>@endif
                </div>
                @if (! empty($step['hint']))<div class="text-xs text-fg-3">{{ $step['hint'] }}</div>@endif
            </div>
        </li>
    @endforeach
</ol>
