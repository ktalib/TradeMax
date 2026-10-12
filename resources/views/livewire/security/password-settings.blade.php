<section class="card">
    <header class="card-h">
        <div>
            <h2 class="text-[15px] font-semibold">{{ __('Password') }}</h2>
            <p class="text-xs text-fg-3">{{ __('Changing it signs out your other devices.') }}</p>
        </div>
        @unless ($editing)
            <x-ui.button variant="secondary" size="sm" wire:click="open">{{ __('Change') }}</x-ui.button>
        @endunless
    </header>
    @if ($editing)
        <div class="card-b flex flex-col gap-4">
            <x-ui.input name="current_password" type="password" :label="__('Current password')" wire:model="current_password" autocomplete="current-password" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="password" type="password" :label="__('New password')" wire:model="password" autocomplete="new-password" />
                <x-ui.input name="password_confirmation" type="password" :label="__('Confirm new password')" wire:model="password_confirmation" autocomplete="new-password" />
            </div>
            @include('livewire.security.partials.step-up')
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button class="flex-1" wire:click="save" wire:loading.attr="disabled">{{ __('Change password') }}</x-ui.button>
            </div>
        </div>
    @endif
</section>
