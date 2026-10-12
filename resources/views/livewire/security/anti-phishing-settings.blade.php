<section class="card">
    <header class="card-h">
        <div>
            <h2 class="text-[15px] font-semibold">{{ __('Anti-phishing code') }}</h2>
            <p class="text-xs text-fg-3">{{ __('Shown at the top of every email we send. A fake email won\'t know it.') }}</p>
        </div>
        @unless ($editing)
            <x-ui.button variant="secondary" size="sm" wire:click="open">{{ $current ? __('Change') : __('Set code') }}</x-ui.button>
        @endunless
    </header>
    <div class="card-b flex flex-col gap-4">
        @if ($current && ! $editing)
            <div class="font-mono text-[15px]">{{ $current }}</div>
        @elseif (! $editing)
            <p class="text-[13px] text-fg-2">{{ __('Not set yet. We recommend setting one now.') }}</p>
        @endif

        @if ($editing)
            <x-ui.input name="anti_phishing_code" :label="__('Your code')" wire:model="anti_phishing_code" maxlength="20" :hint="__('4 to 20 letters, numbers or hyphens, for example ORCHID-42.')" />
            <x-ui.input name="totp_code" :label="__('Authenticator code')" wire:model="totpCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button class="flex-1" wire:click="save" wire:loading.attr="disabled">{{ __('Save code') }}</x-ui.button>
            </div>
        @endif
    </div>
</section>
