<x-mail::message>
@if ($user->anti_phishing_code)
<x-mail::panel>
{{ __('Your anti-phishing code') }}: **{{ $user->anti_phishing_code }}**
{{ __('Emails from us always show this code. If it is missing or wrong, the email is not from us.') }}
</x-mail::panel>
@endif

# {{ $event->subject() }}

{{ __('Hi :name,', ['name' => $user->name]) }}

{{ __('We are letting you know about a change to your account security.') }}

<x-mail::table>
| | |
|:--|:--|
| **{{ __('What happened') }}** | {{ $event->subject() }} |
@isset($context['device'])
| **{{ __('Device') }}** | {{ $context['device'] }} |
@endisset
@if (! empty($context['ip']))
| **{{ __('IP address') }}** | {{ $context['ip'] }} |
@endif
| **{{ __('When (UTC)') }}** | {{ \Carbon\CarbonImmutable::parse($context['time'] ?? 'now')->format('Y-m-d H:i') }} |
</x-mail::table>

@if ($event->locksWithdrawals())
{{ __('For your protection, withdrawals are paused for 24 hours.') }}
@endif

**{{ __('Was this not you?') }}** {{ __('Freeze your account now. Withdrawals and API keys stop immediately, then contact support.') }}

<x-mail::button :url="$freezeUrl" color="error">
{{ __('Freeze my account') }}
</x-mail::button>

{{ __('We will never ask for your password, codes or recovery phrases. Never share them, not even with our staff.') }}

{{ config('app.name') }}
</x-mail::message>
