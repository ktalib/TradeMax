<x-mail::message>
@if ($antiPhishingCode)
<x-mail::panel>
{{ __('Your anti-phishing code') }}: **{{ $antiPhishingCode }}**
</x-mail::panel>
@endif

# {{ __('Your verification code') }}

{{ __('Use this code :reason:', ['reason' => $reason]) }}

<x-mail::panel>
<span style="font-size:28px;letter-spacing:6px;font-weight:700">{{ $code }}</span>
</x-mail::panel>

{{ __('It works once and expires in :minutes minutes.', ['minutes' => $minutes]) }}

{{ __('If you did not ask for this code, someone may know your password. Change it and freeze your account from the security page.') }}

{{ __('We will never ask for this code by phone, chat or email.') }}

{{ config('app.name') }}
</x-mail::message>
