<section class="card">
    <header class="card-h">
        <div>
            <h2 class="text-[15px] font-semibold">{{ __('Two-step verification') }}</h2>
            <p class="text-xs text-fg-3">{{ __('Authenticator app') }}@if ($confirmedAt) · {{ __('on since :date', ['date' => $confirmedAt->isoFormat('ll')]) }}@endif</p>
        </div>
        <x-ui.badge tone="success" dot>{{ __('On') }}</x-ui.badge>
    </header>
    <div class="card-b flex flex-col gap-4">
        @if ($recoveryCodes !== [])
            <div class="rounded-[var(--radius)] border border-border bg-surface-2 p-4">
                <p class="text-[13px] font-semibold">{{ __('Your new recovery codes') }}</p>
                <p class="text-xs text-fg-3">{{ __('Save them now. The old codes no longer work and these are shown only once.') }}</p>
                <div class="mt-3 grid grid-cols-2 gap-1.5 font-mono text-[13px]" dir="ltr">
                    @foreach ($recoveryCodes as $code)<span>{{ $code }}</span>@endforeach
                </div>
            </div>
        @endif

        @if ($mode === null)
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" size="sm" wire:click="start('recovery')">{{ __('New recovery codes') }}</x-ui.button>
                <x-ui.button variant="ghost" size="sm" wire:click="start('reset')">{{ __('Move to a new authenticator') }}</x-ui.button>
            </div>
        @else
            <p class="text-[13px] text-fg-2">
                {{ $mode === 'reset'
                    ? __('Your current authenticator stops working and you will set up a new one straight away. Withdrawals pause for 24 hours.')
                    : __('This replaces all your recovery codes.') }}
            </p>
            @include('livewire.security.partials.step-up')
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                @if ($mode === 'reset')
                    <x-ui.button variant="danger" class="flex-1" wire:click="resetAuthenticator" wire:loading.attr="disabled">{{ __('Remove and set up again') }}</x-ui.button>
                @else
                    <x-ui.button class="flex-1" wire:click="regenerate" wire:loading.attr="disabled">{{ __('Create new codes') }}</x-ui.button>
                @endif
            </div>
        @endif
    </div>
</section>
