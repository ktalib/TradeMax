<section class="card">
    <header class="card-h">
        <div>
            <h2 class="text-[15px] font-semibold">{{ __('Something wrong?') }}</h2>
            <p class="text-xs text-fg-3">{{ __('If someone else may have access, freeze the account.') }}</p>
        </div>
    </header>
    <div class="card-b flex flex-col gap-3">
        <p class="text-[13px] text-fg-2">{{ __('Freezing stops withdrawals and API keys at once and signs out every other device. Unfreezing needs a check by support.') }}</p>
        @if ($confirming)
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="$set('confirming', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" class="flex-1" wire:click="freeze" wire:loading.attr="disabled">{{ __('Yes, freeze my account') }}</x-ui.button>
            </div>
        @else
            <x-ui.button variant="danger" wire:click="$set('confirming', true)">{{ __('Freeze my account') }}</x-ui.button>
        @endif
    </div>
</section>
