<x-layouts.guest :title="__('Two-step verification')">
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Two-step verification') }}</h1>

    <div x-data="{ recovery: false }">
        <p class="mt-1 mb-6 text-[13px] text-fg-2" x-show="!recovery">{{ __('Enter the 6-digit code from your authenticator app.') }}</p>
        <p class="mt-1 mb-6 text-[13px] text-fg-2" x-show="recovery" x-cloak>{{ __('Enter one of the recovery codes you saved when you set up two-step verification. Each code works once.') }}</p>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-4">
            @csrf
            <div x-show="!recovery">
                <x-ui.input name="code" :label="__('Authenticator code')" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-ui.input name="recovery_code" :label="__('Recovery code')" autocomplete="off" x-bind:disabled="!recovery" />
            </div>
            <x-ui.button type="submit" size="lg" block>{{ __('Continue') }}</x-ui.button>
        </form>

        <button type="button" class="mt-4 text-[13px] font-semibold text-brand hover:underline" @click="recovery = !recovery">
            <span x-show="!recovery">{{ __('Use a recovery code instead') }}</span>
            <span x-show="recovery" x-cloak>{{ __('Use the authenticator app') }}</span>
        </button>
    </div>
</x-layouts.guest>
