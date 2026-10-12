<div class="grid items-start gap-5 lg:grid-cols-2">
    <section class="card">
        <header class="card-h">
            <div>
                <h2 class="text-[15px] font-semibold">{{ __('Signed-in sessions') }}</h2>
                <p class="text-xs text-fg-3">{{ __('Sessions end after :minutes minutes without activity.', ['minutes' => config('session.lifetime')]) }}</p>
            </div>
            @if (count($sessions) > 1)
                <x-ui.button variant="secondary" size="sm" wire:click="signOutOthers">{{ __('Sign out all others') }}</x-ui.button>
            @endif
        </header>
        <div class="card-b flex flex-col">
            @foreach ($sessions as $s)
                @php($device = new \App\Domain\Identity\Models\UserDevice(['user_agent' => $s['user_agent']]))
                <div class="flex items-center justify-between gap-3 border-t border-border py-3 text-[13px] first:border-t-0 first:pt-0">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ $device->label() }} @if ($s['id'] === $currentSessionId)<x-ui.badge tone="brand" class="ms-1">{{ __('This session') }}</x-ui.badge>@endif</div>
                        <div class="text-xs text-fg-3">{{ \App\Support\Privacy\Mask::ip($s['ip']) }} · {{ $s['last_active']->diffForHumans() }}</div>
                    </div>
                    @if ($s['id'] !== $currentSessionId)
                        <x-ui.button variant="ghost" size="sm" wire:click="signOut('{{ $s['id'] }}')">{{ __('Sign out') }}</x-ui.button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <header class="card-h">
            <div>
                <h2 class="text-[15px] font-semibold">{{ __('Devices') }}</h2>
                <p class="text-xs text-fg-3">{{ __('New devices need an emailed code. Trusted devices skip it for 30 days.') }}</p>
            </div>
        </header>
        <div class="card-b flex flex-col">
            @foreach ($devices as $device)
                <div class="flex items-center justify-between gap-3 border-t border-border py-3 text-[13px] first:border-t-0 first:pt-0">
                    <div class="min-w-0">
                        <div class="font-semibold">
                            {{ $device->label() }}
                            @if ($device->id === $currentDeviceId)<x-ui.badge tone="brand" class="ms-1">{{ __('This device') }}</x-ui.badge>@endif
                            @if ($device->isTrusted())<x-ui.badge tone="success" class="ms-1">{{ __('Trusted') }}</x-ui.badge>@endif
                        </div>
                        <div class="text-xs text-fg-3">{{ \App\Support\Privacy\Mask::ip($device->ip) }} · {{ __('last seen :when', ['when' => $device->last_seen_at->diffForHumans()]) }}</div>
                    </div>
                    @if ($device->verified_at !== null && $device->id !== $currentDeviceId)
                        <x-ui.button variant="ghost" size="sm" wire:click="forget({{ $device->id }})">{{ __('Forget') }}</x-ui.button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="card lg:col-span-2">
        <header class="card-h">
            <h2 class="text-[15px] font-semibold">{{ __('Recent sign-in activity') }}</h2>
        </header>
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('When') }}</th><th>{{ __('Result') }}</th><th>{{ __('IP address') }}</th><th>{{ __('Device') }}</th>
            </x-slot:head>
            @foreach ($attempts as $attempt)
                @php($agent = new \App\Domain\Identity\Models\UserDevice(['user_agent' => $attempt->user_agent]))
                <tr>
                    <td data-label="{{ __('When') }}">{{ $attempt->created_at->setTimezone(auth()->user()->timezone)->isoFormat('lll') }}</td>
                    <td data-label="{{ __('Result') }}">
                        @if ($attempt->success)
                            <x-ui.badge tone="success" dot>{{ __('Signed in') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="danger" dot>{{ $attempt->reason === 'two_factor_failed' ? __('Wrong 2FA code') : __('Failed') }}</x-ui.badge>
                        @endif
                    </td>
                    <td data-label="{{ __('IP address') }}">{{ \App\Support\Privacy\Mask::ip($attempt->ip) }}</td>
                    <td data-label="{{ __('Device') }}">{{ $agent->label() }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </section>
</div>
