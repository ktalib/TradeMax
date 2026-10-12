<section class="card">
    <header class="card-h">
        <div>
            <h2 class="text-[15px] font-semibold">{{ __('Email and phone') }}</h2>
            <p class="text-xs text-fg-3">{{ __('Where we send codes and security alerts.') }}</p>
        </div>
    </header>
    <div class="card-b flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3 text-[13.5px]">
            <div><div class="text-xs text-fg-3">{{ __('Email') }}</div><div>{{ $maskedEmail }}</div></div>
            @if ($editing === null)<x-ui.button variant="secondary" size="sm" wire:click="edit('email')">{{ __('Change') }}</x-ui.button>@endif
        </div>

        @if ($editing === 'email')
            <x-ui.input name="email" type="email" :label="__('New email address')" wire:model="email" autocomplete="email" :hint="__('We will send a confirmation link there. Alerts go to both addresses.')" />
            @include('livewire.security.partials.step-up')
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button class="flex-1" wire:click="requestEmail" wire:loading.attr="disabled">{{ __('Send confirmation link') }}</x-ui.button>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 border-t border-border pt-4 text-[13.5px]">
            <div><div class="text-xs text-fg-3">{{ __('Phone') }}</div><div class="num" dir="ltr">{{ $maskedPhone }}</div></div>
            @if ($editing === null)<x-ui.button variant="secondary" size="sm" wire:click="edit('phone')">{{ __('Change') }}</x-ui.button>@endif
        </div>

        @if ($editing === 'phone')
            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                <x-ui.select name="phone_country" :label="__('Country')" :options="$countries" wire:model="phone_country" />
                <x-ui.input name="phone" type="tel" :label="__('New mobile number')" wire:model="phone" inputmode="tel" />
            </div>
            @include('livewire.security.partials.step-up')
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button class="flex-1" wire:click="requestPhone" wire:loading.attr="disabled">{{ __('Text a code to the new number') }}</x-ui.button>
            </div>
        @endif

        @if ($editing === 'phone-code')
            <x-ui.input name="code" :label="__('Code sent to your new number')" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button class="flex-1" wire:click="confirmPhone" wire:loading.attr="disabled">{{ __('Confirm new number') }}</x-ui.button>
            </div>
        @endif
    </div>
</section>
