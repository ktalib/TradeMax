<x-layouts.guest :title="__('Create your account')" :captcha="(bool) $captchaSiteKey" wide>
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Create your account') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('Start with where you live. We can only open accounts in countries we serve.') }}</p>

    @include('auth.partials.status')

    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-4"
          x-data="{ country: @js(old('residence_country', '')), codes: @js($callingCodes) }"
          x-init="$refs.tz.value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''">
        @csrf
        <input type="hidden" name="timezone" x-ref="tz">

        <x-ui.select name="residence_country" :label="__('Country of residence')" :options="$countries" :placeholder="__('Choose a country')" x-model="country" required />

        <x-ui.input name="name" :label="__('Full legal name')" autocomplete="name" :hint="__('As it appears on your ID. You will need it for verification.')" required />
        <x-ui.input name="email" type="email" :label="__('Email')" autocomplete="email" required />

        <div>
            <label class="field-label" for="f-phone">{{ __('Mobile number') }}</label>
            <div @class(['field', 'invalid' => $errors->has('phone')])>
                <span class="field-affix num" x-text="country && codes[country] ? '+' + codes[country] : '+'"></span>
                <input id="f-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel-national" value="{{ old('phone') }}" required
                       @if ($errors->has('phone')) aria-invalid="true" aria-describedby="f-phone-error" @endif>
            </div>
            @error('phone')<p class="field-error" id="f-phone-error">{{ $message }}</p>@enderror
            <p class="field-hint">{{ __('We send security codes here by SMS.') }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="password" type="password" :label="__('Password')" autocomplete="new-password" :hint="__('At least 10 characters. A phrase of unrelated words works well.')" required />
            <x-ui.input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" required />
        </div>

        <x-ui.input name="referral_code" :label="__('Referral code (optional)')" autocomplete="off" />

        <label class="flex items-start gap-2.5 text-[13px] text-fg-2">
            <input type="checkbox" name="terms" value="1" class="mt-1 accent-[var(--brand)]" @checked(old('terms')) required>
            <span>{{ __('I accept the Terms of Service, Privacy Policy and Risk Disclosure. I understand crypto prices are volatile and I can lose money.') }}</span>
        </label>
        @error('terms')<p class="field-error" style="margin-top:-8px">{{ $message }}</p>@enderror

        @if ($captchaSiteKey)
            <div class="cf-turnstile" data-sitekey="{{ $captchaSiteKey }}" data-theme="auto"></div>
        @endif

        <x-ui.button type="submit" size="lg" block>{{ __('Create account') }}</x-ui.button>
    </form>

    <x-slot:below>
        {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">{{ __('Sign in') }}</a>
    </x-slot:below>
</x-layouts.guest>
