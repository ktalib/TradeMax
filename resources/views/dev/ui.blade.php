{{-- Developer-only component gallery (Phase 0 acceptance). Not user-facing, so copy here is not translated. --}}
<x-layouts.app title="Component gallery">
    <x-slot:header>
        <span class="text-[13px] text-fg-3">Phase 0 · design system</span>
    </x-slot:header>

    {{-- ?theme=light|dark|system lets screenshots and reviewers open a specific theme directly. --}}
    <script>
        (() => { const t = new URLSearchParams(location.search).get('theme'); if (['light', 'dark', 'system'].includes(t)) document.documentElement.dataset.theme = t; })();
    </script>

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">Component gallery</h1>
            <p class="mt-1.5 max-w-[62ch] text-fg-2">Every component in <code class="font-mono text-[13px]">resources/views/components/ui</code>, rendered with sample data. Check it in both themes at 360px and 1280px wide.</p>
        </div>
        <div x-data="themeSwitcher">
            <x-ui.segmented-control label="Theme" :options="['dark' => 'Dark', 'light' => 'Light', 'system' => 'System']" value="dark" x-model="theme" @change="set($event.detail)" />
        </div>
    </div>

    <section aria-labelledby="g-buttons" class="flex flex-col gap-3">
        <h2 id="g-buttons" class="eyebrow">Buttons</h2>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button>Deposit</x-ui.button>
            <x-ui.button variant="secondary">Withdraw</x-ui.button>
            <x-ui.button variant="ghost">Cancel</x-ui.button>
            <x-ui.button variant="danger">Freeze account</x-ui.button>
            <x-ui.button variant="buy">Buy BTC</x-ui.button>
            <x-ui.button variant="sell">Sell BTC</x-ui.button>
            <x-ui.button size="sm" variant="secondary">Small</x-ui.button>
            <x-ui.button loading>Placing order</x-ui.button>
            <x-ui.button href="#g-buttons" variant="secondary">Link button</x-ui.button>
        </div>
    </section>

    <section aria-labelledby="g-badges" class="flex flex-col gap-3">
        <h2 id="g-badges" class="eyebrow">Badges</h2>
        <div class="flex flex-wrap gap-2">
            <x-ui.badge tone="success" dot>Credited</x-ui.badge>
            <x-ui.badge tone="warning" dot>Pending approval</x-ui.badge>
            <x-ui.badge tone="danger" dot>Rejected</x-ui.badge>
            <x-ui.badge tone="info" dot>Locked</x-ui.badge>
            <x-ui.badge tone="brand">This device</x-ui.badge>
            <x-ui.badge tone="accent">Flexible</x-ui.badge>
            <x-ui.badge>Neutral</x-ui.badge>
        </div>
    </section>

    <section aria-labelledby="g-stats" class="flex flex-col gap-3">
        <h2 id="g-stats" class="eyebrow">Stat cards, amounts and PnL</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.stat-card label="Portfolio value" value="$43,412.18" delta="0.0181" delta-label="today" :sparkline="$sparkline" />
            <x-ui.stat-card label="BTC/USDT" value="67,420.50" delta="-0.0118" delta-label="24h" :sparkline="array_reverse($sparkline)" />
            <x-ui.stat-card label="Fee revenue · 24h" value="$1,204.66" />
        </div>
        <x-ui.card>
            <div class="flex flex-wrap gap-x-8 gap-y-2 text-[14px]">
                <span>Amount: <x-ui.amount value="1234567.891234" asset="USDT" :decimals="2" /></span>
                <span>Trimmed: <x-ui.amount value="0.42150000" asset="BTC" :decimals="8" trim /></span>
                <span>Gain: <x-ui.pnl value="0.0231" percent /></span>
                <span>Loss: <x-ui.pnl value="-0.0344" percent /></span>
                <span>Flat: <x-ui.pnl value="0" percent /></span>
                <span>Absolute: <x-ui.pnl value="612.4" suffix="USDT" /></span>
            </div>
        </x-ui.card>
    </section>

    <section aria-labelledby="g-table" class="flex flex-col gap-3">
        <h2 id="g-table" class="eyebrow">Table with asset icons</h2>
        <x-ui.card title="Spot wallet" :padded="false">
            <x-slot:actions><x-ui.button size="sm" variant="ghost">View all</x-ui.button></x-slot:actions>
            <x-ui.table>
                <x-slot:head>
                    <th>Asset</th><th class="end">Balance</th><th class="end">24h</th><th class="end"></th>
                </x-slot:head>
                @foreach ($balances as $b)
                    <tr>
                        <td data-label="Asset"><div class="flex items-center gap-3"><x-ui.asset-icon :symbol="$b['symbol']" /><div><div class="font-semibold">{{ $b['symbol'] }}</div><div class="text-xs text-fg-3">{{ $b['name'] }}</div></div></div></td>
                        <td data-label="Balance" class="end"><x-ui.amount :value="$b['total']" :decimals="$b['decimals']" trim /></td>
                        <td data-label="24h" class="end"><x-ui.pnl :value="$b['change']" percent /></td>
                        <td class="end"><x-ui.button size="sm" variant="ghost">Trade</x-ui.button></td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </section>

    <section aria-labelledby="g-forms" class="flex flex-col gap-3">
        <h2 id="g-forms" class="eyebrow">Forms</h2>
        <x-ui.card>
            <form class="grid gap-4 md:grid-cols-2" onsubmit="event.preventDefault()">
                <x-ui.input name="amount" label="Amount" suffix="USDT" max="12480.55" inputmode="decimal" placeholder="0.00" hint="Available: 12,480.55 USDT" />
                <x-ui.input name="price" label="Price" prefix="Limit" suffix="USDT" inputmode="decimal" value="67420.50" />
                <x-ui.select name="network" label="Network" :options="['TRC20' => 'TRON (TRC20)', 'ERC20' => 'Ethereum (ERC20)', 'BEP20' => 'BNB Smart Chain (BEP20)']" selected="TRC20" />
                <div class="flex flex-col gap-2">
                    <span class="field-label" style="margin:0">Order type</span>
                    <x-ui.segmented-control name="order_type" label="Order type" :options="['limit' => 'Limit', 'market' => 'Market', 'stop' => 'Stop-limit']" />
                </div>
                <x-ui.otp-input name="email_code" label="Code sent to your email" />
                <x-ui.address label="Your deposit address" value="TQx7mWb3nR9vKc2LpY4sHf8dJ6tGa1EzUo" />
            </form>
        </x-ui.card>
    </section>

    <section aria-labelledby="g-tabs" class="flex flex-col gap-3">
        <h2 id="g-tabs" class="eyebrow">Tabs, stepper, progress, countdown</h2>
        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card :padded="false">
                <x-ui.tabs :tabs="['open' => 'Open orders', 'history' => 'Order history']">
                    <x-ui.tab-panel name="open">
                        <x-ui.empty-state title="No open orders" description="Orders you place show here until they fill or you cancel them.">
                            <x-slot:action><x-ui.button size="sm">Place an order</x-ui.button></x-slot:action>
                        </x-ui.empty-state>
                    </x-ui.tab-panel>
                    <x-ui.tab-panel name="history">
                        <div class="p-5"><x-ui.skeleton :lines="4" /></div>
                    </x-ui.tab-panel>
                </x-ui.tabs>
            </x-ui.card>
            <x-ui.card title="Verify your identity" subtitle="Step 3 of 4">
                <x-ui.stepper :steps="$steps" :current="3" />
                <div class="mt-5 flex flex-col gap-4">
                    <x-ui.progress value="0.6" label="ETH Fixed · day 18 of 30" />
                    <div class="text-[13px] text-fg-2">Matures in <x-ui.countdown :until="$maturesAt" /></div>
                </div>
            </x-ui.card>
        </div>
    </section>

    <section aria-labelledby="g-overlays" class="flex flex-col gap-3">
        <h2 id="g-overlays" class="eyebrow">Modal, toast, tooltip</h2>
        <div class="flex flex-wrap items-center gap-3" x-data>
            <x-ui.button variant="secondary" @click="$dispatch('open-modal', 'subscribe')">Open modal</x-ui.button>
            <x-ui.button variant="secondary" @click="$dispatch('toast', { message: 'Order placed: 0.0150 BTC at 67,420.50' })">Show toast</x-ui.button>
            <x-ui.tooltip text="Fees are charged in the asset you receive.">
                <x-ui.button variant="ghost">Hover for tooltip</x-ui.button>
            </x-ui.tooltip>
        </div>
        <x-ui.modal name="subscribe" title="Subscribe to USDT Flexible">
            <x-ui.input name="sub_amount" label="Amount" suffix="USDT" inputmode="decimal" placeholder="Min 10" />
            <p class="text-[13px] text-fg-2">Rewards are paid daily from the yield pool and can change. They aren't guaranteed.</p>
            <x-slot:footer>
                <x-ui.button variant="secondary" @click="$dispatch('close-modal', 'subscribe')">Cancel</x-ui.button>
                <x-ui.button class="flex-1" @click="$dispatch('close-modal', 'subscribe'); $dispatch('toast', { message: 'Subscribed (demo)' })">Subscribe</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    </section>
</x-layouts.app>
