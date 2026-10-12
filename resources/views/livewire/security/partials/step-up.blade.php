{{-- Expects the WithStepUp trait on the component. --}}
<div class="flex flex-col gap-3 rounded-[var(--radius)] border border-border bg-surface-2 p-4">
    <p class="text-[13px] font-semibold">{{ __('Confirm it\'s you') }}</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-ui.input name="totp_code" :label="__('Authenticator code')" wire:model="totpCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
        <div>
            <x-ui.input name="email_code" :label="__('Email code')" wire:model="emailCode" inputmode="numeric" autocomplete="off" maxlength="6" />
            <button type="button" class="mt-1.5 text-xs font-semibold text-brand hover:underline" wire:click="sendStepUpCode" wire:loading.attr="disabled" wire:target="sendStepUpCode">
                {{ $emailCodeSent ? __('Send a new code') : __('Email me a code') }}
            </button>
        </div>
    </div>
</div>
