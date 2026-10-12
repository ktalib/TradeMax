<section class="card">
    <header class="card-h">
        <div>
            <h2 class="text-[15px] font-semibold">{{ __('API keys') }}</h2>
            <p class="text-xs text-fg-3">{{ __('For trading bots. API keys can never withdraw funds.') }}</p>
        </div>
        @unless ($creating)
            <x-ui.button variant="secondary" size="sm" wire:click="open">{{ __('Create key') }}</x-ui.button>
        @endunless
    </header>
    <div class="card-b flex flex-col gap-4">
        @if ($issued)
            <div class="flex flex-col gap-3 rounded-[var(--radius)] border border-[color-mix(in_srgb,var(--warning)_35%,transparent)] bg-warning-soft p-4">
                <p class="text-[13px] font-semibold text-warning">{{ __('Copy your key and secret now. The secret is never shown again.') }}</p>
                <x-ui.address :label="__('API key')" :value="$issued['key']" />
                <x-ui.address :label="__('Secret')" :value="$issued['secret']" />
            </div>
        @endif

        @if ($creating)
            <x-ui.input name="name" :label="__('Label')" wire:model="name" maxlength="64" :hint="__('For example: Grid bot on my VPS')" />
            <fieldset class="flex flex-col gap-2">
                <legend class="field-label">{{ __('Permissions') }}</legend>
                @foreach ($permissionOptions as $option)
                    <label class="flex items-center gap-2.5 text-[13px]"><input type="checkbox" value="{{ $option->value }}" wire:model="permissions" class="accent-[var(--brand)]"> {{ $option->label() }}</label>
                @endforeach
                @error('permissions')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>
            <x-ui.input name="ip_whitelist" :label="__('Allowed IP addresses (recommended)')" wire:model="ip_whitelist" :hint="__('Separate with commas. Leave empty to allow any IP.')" />
            @include('livewire.security.partials.step-up')
            <div class="flex gap-2">
                <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button class="flex-1" wire:click="create" wire:loading.attr="disabled">{{ __('Create key') }}</x-ui.button>
            </div>
        @endif

        @forelse ($keys as $key)
            <div class="flex items-center justify-between gap-3 border-t border-border pt-3 text-[13px]">
                <div class="min-w-0">
                    <div class="truncate font-semibold">{{ $key->name }}</div>
                    <div class="font-mono text-xs text-fg-3">{{ $key->key_prefix }}… · {{ $key->permissions->map(fn ($p) => $p->value)->join(', ') }}</div>
                </div>
                @if ($key->isActive())
                    <x-ui.button variant="ghost" size="sm" wire:click="revoke('{{ $key->public_id }}')" wire:confirm="{{ __('Revoke this key? Bots using it stop working immediately.') }}">{{ __('Revoke') }}</x-ui.button>
                @else
                    <x-ui.badge>{{ __('Revoked') }}</x-ui.badge>
                @endif
            </div>
        @empty
            @unless ($creating)
                <p class="text-[13px] text-fg-2">{{ __('No API keys yet.') }}</p>
            @endunless
        @endforelse
    </div>
</section>
