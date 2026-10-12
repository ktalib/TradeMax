<x-layouts.guest :title="__('Set up two-step verification')" wide>
    @include('onboarding.partials.steps', ['current' => 3])

    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Set up your authenticator app') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('Two-step verification is required on every account. Use Google Authenticator, Microsoft Authenticator, 1Password or any app that supports TOTP. SMS is never your only second step.') }}</p>

    @include('auth.partials.status')

    <ol class="flex flex-col gap-5 text-[13.5px]">
        <li class="flex flex-col gap-3">
            <span class="font-semibold">{{ __('1. Scan this QR code with the app') }}</span>
            <div class="flex flex-wrap items-center gap-5">
                <div class="rounded-[var(--radius)] bg-white p-3 [&_svg]:size-[168px]" aria-label="{{ __('QR code for your authenticator app') }}">{!! $qrSvg !!}</div>
                <div class="min-w-0 flex-1">
                    <p class="text-fg-2">{{ __('Can\'t scan? Enter this key in the app instead:') }}</p>
                    <x-ui.address :value="$secret" class="mt-2" />
                </div>
            </div>
        </li>
        <li>
            <form method="POST" action="{{ route('onboarding.two-factor.confirm') }}" class="flex flex-col gap-4">
                @csrf
                <span class="font-semibold">{{ __('2. Enter the 6-digit code the app shows') }}</span>
                <x-ui.input name="code" :label="__('Authenticator code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                <x-ui.button type="submit" size="lg" block>{{ __('Turn on two-step verification') }}</x-ui.button>
            </form>
        </li>
    </ol>
</x-layouts.guest>
