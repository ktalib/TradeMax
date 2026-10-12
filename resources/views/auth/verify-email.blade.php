<x-layouts.guest :title="__('Confirm your email')">
    <span class="mb-4 grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand"><x-ui.icon name="inbox" :size="22" /></span>
    <h1 class="font-display text-2xl font-semibold tracking-tight">{{ __('Confirm your email') }}</h1>
    <p class="mt-1 mb-6 text-[13px] text-fg-2">{{ __('We sent a link to :email. Open it on this device to continue.', ['email' => \App\Support\Privacy\Mask::email(auth()->user()->email)]) }}</p>

    @if (session('status') === 'verification-link-sent')
        <div class="mb-5 rounded-[var(--radius)] bg-up-soft px-3.5 py-3 text-[13px] text-up" role="status">{{ __('A new link is on its way.') }}</div>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="lg" block>{{ __('Send the link again') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="ghost" block>{{ __('Sign out') }}</x-ui.button>
        </form>
    </div>
</x-layouts.guest>
