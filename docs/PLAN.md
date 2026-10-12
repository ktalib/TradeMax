# Implementation Plan — TradeMax

> Full program plan for Phases 0–12. Companion to `docs/PROGRESS.md` (status tracker) and the master build prompt (`crypto-platform-agent-prompt (2).md`, the source of truth for scope). This document is the working map: what gets built, in what order, and how each phase is proven done.
>
> **Precedence:** where this plan or an ADR in `docs/DECISIONS.md` contradicts the master prompt, this plan and the ADRs win. ADR-0003 lists the corrections made to the master prompt's spec.

## 1. How to use this plan

- Phases are **strictly sequential**. A phase starts only when the previous phase's exit gate passes (see `docs/PROGRESS.md`).
- Each phase has: **Goal**, **Deliverables**, **Schema**, **Jobs/Events**, **UI**, **Acceptance**, **Exit gate**.
- After each phase: `composer test && composer analyse && composer lint` then `npm run build`. Commit green phases with Conventional Commits.
- Non-obvious choices are appended to `docs/DECISIONS.md` as ADRs as they are made.
- Cross-cutting foundations (Section 3) are built once in Phase 0 and reused; later phases only add to them.
- Open operator inputs that block specific phases are tracked in Section 9.

## 2. Phase gates & critical path

```
Phase 0  Foundation + Design System
   └─ Phase 1  Identity & Security
        └─ Phase 2  Assets & Ledger            ◄── hard dependency for all money
             ├─ Phase 3  KYC
             ├─ Phase 4  Deposits (Rail A/B)
             ├─ Phase 5  Withdrawals
             │    └─ Phase 6  Notifications
             ├─ Phase 7  Trading  ──► Phase 8 Staking ──► Phase 9 Copy Trading ──► Phase 9b Signals
             └─ Phase 10 Compliance / Admin / Support / Referrals
                  └─ Phase 11 i18n & extras
                       └─ Phase 12 API / E2E / Hardening / Deploy
```

- **Critical path:** 0 → 1 → 2. Nothing money-related starts before Phase 2.
- **Phase 6 (Notifications)** is a dependency for acceptance of Phases 4, 5, 7, 8, 9 (they assert notifications), so it lands before Trading.
- **Phase 7 (Trading)** is the heaviest; Phases 8–9 depend on its engine, fills, and ledger settlement.
- **Phase 10** can begin partially in parallel conceptually but is sequenced after 9 to keep the gate model simple.
- **Phase 12** is the only place the public API and OpenAPI surface are built.

## 3. Cross-cutting foundations (built in Phase 0)

| Foundation | Where | Notes |
|---|---|---|
| Money math | `App\Support\Money\Decimal` | bcmath wrapper, scale 18; no float/double anywhere in `App\Domain` |
| Public IDs | `App\Support\Concerns\HasPublicUlid` | ULID `public_id` for anything exposed in URLs/APIs |
| Domain skeleton | `app/Domain/<Module>/{...}` | All 14 modules created as directories with the standard subtrees (Signals is added in Phase 9b) |
| Container wiring | `app/Providers/DomainServiceProvider` | Binds repository `Contracts/` interfaces to Eloquent implementations |
| Config surface | `config/platform.php`, `payments.php`, `kyc.php`, `sms.php`, `trading.php` | Defaults live here; admin-editable values in `settings`, cached with tag invalidation |
| State machines | backed Enums + `canTransitionTo()` | One per status column; illegal transitions throw |
| Idempotency | UNIQUE `idempotency_key` / provider event ids | Enforced at the DB layer, checked in Actions |
| Audit | `spatie/laravel-activitylog` + custom immutable `audit_logs` | Append-only; DB grant denies UPDATE/DELETE |
| Lazy-load guard | `Model::preventLazyLoading()` | Non-production, in `AppServiceProvider` |
| Security headers | `SecurityHeaders` middleware | CSP (nonce), HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy |
| Design system | `resources/css/tokens.css` + `@theme` + `resources/views/components/ui/*` | Tokens only, no raw hex in Blade |

### 3.1 Phase 0 package set

Framework/tooling: Laravel 12, Reverb, Pulse, Sentry, Pest 3, Larastan (lvl 8), Pint, Rector. **No Sail, no Horizon** (ADR-0004): Horizon needs `pcntl`/`posix`, which Windows PHP doesn't have; queues run as plain `queue:work` workers, monitored with Pulse + a Filament failed-jobs page.
Domain: `spatie/laravel-permission`, `spatie/laravel-activitylog`, `spatie/laravel-backup`, `pragmarx/google2fa`, `simplesoftwareio/simple-qrcode`, `intervention/image`, `dedoc/scramble`, `laravel/fortify`, `laravel/sanctum`, `bacon/bacon-qr-code`.
Providers (all behind driver + Fake): payments, chain verifier, payout, SMS, KYC, push, FX, price feed, travel rule, chain analytics, custody, on-ramp.

### 3.2 Invariants enforced from Phase 2 onward

- Per asset, `SUM(debit) == SUM(credit)` for every journal, and across the whole ledger.
- **Sign convention (ADR-0003):** wallet balances are stored from the platform's liability view — a credit increases a wallet, a debit decreases it. **User wallets** (`spot`, `staking`, `copy`) may never have `available` or `locked` < 0. **System wallets** are counter-accounts and may be negative: `system_hot` / `system_cold` go negative as custody is received (their absolute value = crypto the platform holds), `system_demo_faucet` goes negative as it funds demo users. `system_fee`, `system_yield_pool` and `system_suspense` must stay ≥ 0. Each wallet type declares `allowsNegative()` on its Enum.
- Per asset, `-(system_hot + system_cold)` must equal the custody balance reported by `ledger:reconcile` (within tolerance).
- `grep '->update(\[' available '\]'` outside `LedgerService` returns zero results.
- Every externally-triggered money action has a persisted idempotency key.
- No hard deletes on financial tables — status transitions + reversal journals only.

## 4. Queue & scheduler map (lands as phases complete)

| Queue | Priority / process | Introduced |
|---|---|---|
| `critical` | dedicated — ledger, deposit credit, withdrawal processing | Phase 2 |
| `webhooks` | dedicated — provider IPN processing | Phase 4 |
| `notifications` | dedicated — all outbound channels | Phase 6 |
| `trading` | dedicated — order settle, stop triggers | Phase 7 |
| `copy` | dedicated, high priority — mirror orders, settlements | Phase 9 |
| `signals` | — outcome evaluation, indicator generation, stats | Phase 9b |
| `kyc` | — provider sync, rescreen | Phase 3 |
| `reports` | — statements, CSV/XLSX, finance email | Phase 10/11 |
| `default` | — everything else | Phase 0 |

Scheduler entries per master prompt Section 9.1 are added in the phase that owns them, all with `->withoutOverlapping()->onOneServer()`.

## 5. Driver matrix

| Contract | Drivers | Selected by | Fake used in |
|---|---|---|---|
| `PaymentGateway` | nowpayments, coingate, fake | `PAYMENT_GATEWAY` | tests, demo |
| `ChainVerifier` | etherscan/bscscan, trongrid, blockstream/mempool, fake | per network | tests, demo |
| `PayoutDriver` | manual, gateway | `PAYOUT_DRIVER` | tests, demo |
| `SmsDriver` | twilio, vonage, failover, fake | `SMS_DRIVER` | tests, demo |
| `KycProvider` | sumsub, onfido, veriff, fake | `KYC_DRIVER` | tests, demo |
| `PriceFeed` | binance, coingecko, fake | `PRICE_FEED` | tests, demo |
| `PushDriver` | fcm, webpush, fake | installed | tests, demo |
| `FxRateProvider` | openexchangerates, exchangerate.host, fake | `FX_DRIVER` | tests, demo |
| `TravelRuleProvider` | null, notabene, sumsub | `TRAVEL_RULE_DRIVER` | tests |
| `ChainAnalytics` | null, chainalysis, trm, elliptic | `CHAIN_ANALYTICS_DRIVER` | tests |

Every real driver ships with recorded fixtures under `tests/Fixtures/<provider>/` and a signature/replay test.

---

## Phase 0 — Foundation & Design System

**Goal:** a runnable Laravel 12 skeleton on a Windows dev machine with CI, money primitives, and the full dark-first design system, proven by a `/dev/ui` gallery.

**Deliverables**
1. `composer create-project laravel/laravel` (Laravel 12, PHP 8.3); install the Section 3.1 package set; publish configs.
2. Windows local environment (ADR-0004): Laragon (PHP 8.3, MySQL 8), Memurai or Redis-on-Windows for Redis 7, Mailpit Windows binary, local private disk (S3/MinIO optional), Windows Defender as the upload virus scanner (`VIRUS_SCAN_DRIVER=defender|clamav|null`).
3. `composer` scripts instead of a Makefile: `setup`, `dev` (serve + queue workers + reverb + vite + `schedule:work`; no `pail`, it needs `pcntl`), `test`, `lint`, `analyse`, `fresh`, `engine`. Must work on Windows.
4. `composer.json` scripts: `test` (Pest), `analyse` (Larastan), `lint` (Pint), `rectify` (Rector).
5. `.github/workflows/ci.yml`, two jobs: `windows-latest` (production OS; MySQL 8 + Memurai via Chocolatey) and `ubuntu-latest` (service containers, faster feedback). Each runs: composer install → `npm ci && npm run build` → pint --test → larastan → `pest --parallel --coverage --min=85` → `composer audit` → `npm audit --audit-level=high`.
6. `.env.example` complete with comments (master prompt Section 15).
7. `app/Providers/DomainServiceProvider`; all 14 module directory trees; `config/platform.php`, `payments.php`, `kyc.php`, `sms.php`, `trading.php` with defaults + operator values (ADR-0001).
8. `App\Support\Money\Decimal` — full bcmath API (add, sub, mul, div, compare, round, format, toAssetPrecision) with its own Pest suite.
9. `App\Support\Concerns\HasPublicUlid`; `AppServiceProvider` with `preventLazyLoading()`.
10. Design system: `resources/css/tokens.css`, `resources/css/app.css` (`@theme`), Inter + JetBrains Mono self-hosted via `@fontsource`, `resources/views/layouts/app.blade.php` (sidebar rail, top bar, mobile bottom tab bar, theme toggle, RTL logical properties ready).
11. `resources/views/components/ui/*`: button, input, select, segmented-control, card, stat-card, badge, table, tabs, modal/sheet, toast, tooltip, skeleton, empty-state, amount, pnl, asset-icon, address, stepper, progress, countdown, otp-input.
12. Dev-only route `/dev/ui` rendering every component in dark/light.

**Acceptance / exit gate:** CI green; `/dev/ui` renders all components in dark and light at 360px and 1280px; `Decimal` suite green; no float in `App\Domain`.

---

## Phase 1 — Identity & Security

**Goal:** complete auth, 2FA, device/session management, and the security posture that everything else assumes.

**Deliverables**
- Tables: `users`, `user_devices`, `login_attempts`, `api_keys` (exact columns per master prompt Section 5). Encrypted casts: `date_of_birth`, `two_factor_secret`.
- Fortify + TOTP (`pragmarx/google2fa`), optional WebAuthn passkeys behind a flag; SMS never the sole second factor.
- Registration: residence country gate first (blocked stop, restricted show unavailable features) → email + phone (E.164 via `libphonenumber`) + password (min 10, zxcvbn ≥ 3, HIBP k-anonymity) + referral code.
- New device/IP login alert + email OTP; trusted devices 30 days; session list + revoke; idle auto-logout.
- Anti-phishing code; self-service freeze ("I've been hacked") freezes withdrawals + API keys.
- Email/phone/password change & 2FA disable → `withdrawal_locked_until = now()+24h` + notifications to old and new contacts.
- Account freeze/unfreeze state machine; roles/permissions via `spatie/laravel-permission`; `audit_logs`; `SecurityHeaders`.
- Livewire: Login, Register, 2FA challenge, Forgot/Reset, Security centre (2FA, devices, sessions, API keys, anti-phishing), frozen-state page.

**Acceptance / exit gate:** Pest auth suite green (register, 2FA, device alerts, lockouts, rate limits); `security-review` pass recorded in `docs/SECURITY_REVIEW.md`.

---

## Phase 2 — Assets & Ledger (critical foundation)

**Goal:** the double-entry ledger, wallets, system accounts, invariants, snapshots, and reconciliation scaffolding.

**Deliverables**
- Tables: `assets`, `networks`, `asset_networks`, `wallets`, `journals`, `journal_entries`, `wallet_balance_snapshots`.
- `LedgerService::post(JournalDTO)`: transaction → idempotency check (return existing journal on replay) → lock **user** wallets by ascending id (`lockForUpdate`) → validate balances against the sign convention → insert journal + entries (with `balance_after`) → update wallet columns (system wallets via atomic `UPDATE … SET available = available + ?`, no row lock) → commit → dispatch `JournalPosted`. **No snapshot write here** — snapshots are daily only (`ledger:snapshot`, UNIQUE(wallet_id, snapshot_date)).
- **Hot-row avoidance (ADR-0003):** system wallets that are written on every trade/deposit (`system_fee`, `system_hot`) are not locked with `lockForUpdate`, so all markets don't serialise on one row. `system_fee` is only *debited* by referral/admin jobs, which lock it explicitly and check balance.
- System wallets per asset: `system_fee`, `system_yield_pool`, `system_hot`, `system_cold`, `system_suspense` (+ `system_demo_faucet` in demo).
- `LedgerAssertions::assertBalanced()`; `LedgerInvariantTest`.
- Jobs/commands: `ledger:snapshot` (daily 00:01), `ledger:reconcile` (hourly, auto-halt withdrawals on mismatch > tolerance), `ledger:invariant-check` (every 15 min → page on failure).
- Proof-of-liabilities export (Merkle tree of hashed balances) scaffolding.
- Filament: ledger explorer (journals/entries, balanced check), system wallets, reconciliation reports.
- Seeders for assets (BTC, ETH, USDT, BNB, SOL, TRX, USDC), networks, `asset_networks` with fees/confirmations.

**Acceptance / exit gate:** `LedgerInvariantTest` + concurrency tests green (parallel double-spend never negative, idempotent replay yields one journal). Concurrency tests spawn real parallel PHP processes with `proc_open` (`tests/Support/ParallelRunner`), which works on Windows. No `pcntl`.

> **Human review gate:** the ledger is reviewed by a person before Phase 3 starts. No later phase may change `LedgerService` without the same review.

---

## Phase 3 — KYC

**Goal:** tiered verification enforced by middleware and Policies, with fake + real provider drivers and an admin review queue.

**Deliverables**
- Tables: `kyc_tiers`, `kyc_submissions`, `kyc_documents` (document number stored as sha256), `countries`, `fiat_currencies`.
- `KycProvider` contract + drivers: `sumsub`, `onfido`, `veriff`, `fake`; embedded SDK flow (country → document → capture → liveness); signed webhook ingestion.
- Auto-approve rules: green decision + extracted name/DOB match + document not expired + `document_number_hash` unused + no sanctions/PEP hit; else `manual_review`.
- Tier middleware `kyc.tier:N` + Policies; country rules override defaults (require tier to deposit, cap tier, extra docs, disable features).
- Rolling 24h/30d USD limits tracked at request time.
- Document expiry tracking (`kyc:expiry-check` daily) → reminders + tier downgrade; `kyc:rescreen` nightly.
- Livewire KYC centre (stepper) + Filament review queue (side-by-side viewer, provider results, approve/reject templates).

**Acceptance / exit gate:** user reaches T2 with the fake provider; limits enforced; blocked-country registration denied.

---

## Phase 4 — Deposits (Rail A + Rail B)

**Goal:** automated gateway deposits and the manual wallet-transfer verification pipeline, both crediting only through the ledger.

**Deliverables**
- Tables: `deposits`, `deposit_receipts`, `user_deposit_addresses`, `payment_webhooks`.
- `PaymentGateway` + drivers (`nowpayments`, `coingate`, `fake`); `routes/webhooks.php` `POST /webhooks/payments/{provider}` (no CSRF, signature **first**, raw payload stored on UNIQUE(provider,event_id), 200 fast) → `ProcessPaymentWebhook` job.
- `CreditDepositAction` (ledger: debit `system_hot` / credit user `spot.available`); partial/over/wrong-asset handling; fallback poller `deposits:poll-pending` every 5 min.
- **Rail B claim-theft fix (ADR-0003):** a shared platform address alone lets anyone claim another user's on-chain tx by submitting its hash first. Rail B therefore requires one of, per network: (a) a **per-user deposit address** (`user_deposit_addresses`, preferred), or (b) for memo networks, a **per-user memo/tag** that must match on-chain, or (c) for shared-address networks without memo, the **sending address is mandatory** and must be proven by a signed message (EIP-191 / TRON signMessage / BTC signmessage) before any credit. A tx whose destination is a shared address with no matching memo or proof always goes to `manual_review`, never auto-credit. `sending_address` is required, not optional, in case (c).
- Rail B pipeline `VerifyManualDepositJob` (chained): format check → UNIQUE(network,tx_hash) → receipt sha256/perceptual hash + ClamAV + re-encode → on-chain verify (to == platform address, token contract, amount ≥ claimed − tolerance, time window, confirmations); retry schedule 1/3/5/10/20/30/60 min → hourly to 24h.
- `ChainVerifier` contract + drivers (etherscan/bscscan, trongrid, blockstream/mempool, fake) with fixtures for bad signature, replay, partial, overpayment, expired.
- Livewire: Deposit screen (gateway/manual tabs, QR, memo, fee/min/confirmations) + Filament manual-review and credit/reject.
- UI note: receipt is supporting evidence only; crediting is on-chain-verified.

**Acceptance / exit gate:** fixture webhooks credit correctly; duplicate tx/receipt rejected; on-chain verifier fakes pass all cases. (Notification assertions broadened once Phase 6 lands.)

---

## Phase 5 — Withdrawals

**Goal:** whitelist-only withdrawals with OTP step-up, risk scoring, maker-checker, and payout drivers, all reversible via ledger.

**Deliverables**
- Tables: `withdrawal_addresses` (usable after 24h), `withdrawals`.
- Whitelist add flow: email confirm link + TOTP → `usable_after`.
- Request flow: amount + address → fee preview → TOTP + email OTP → `requested` → funds move `available → locked` (ledger).
- `WithdrawalRiskScorer`: new device, within 24h of security change (hard block), first withdrawal, > 80% of balance, deposit-then-withdraw with no trading, blocklisted destination, velocity, open AML alerts.
- Auto-approve rules; `dual_approval_limit_usd` → two distinct finance admins (requester ≠ approver).
- `PayoutDriver` (manual default, gateway) + `withdrawals:sync-status` (every 2 min) + `withdrawals:auto-process`; failure → unlock via reversal journal + notify; user cancel while pending.
- Withdrawal-address-poisoning defence (first 6/last 6 highlighted, resemble-recent-source warning).
- Livewire withdraw screen + Filament withdrawal queue (risk breakdown, approve/2nd approve/reject, tx-hash entry).

**Acceptance / exit gate:** concurrency + reversal tests green; 24h locks enforced for security changes and new whitelist addresses.

---

## Phase 6 — Notifications

**Goal:** the event × channel matrix, branded Mailables, SMS failover, push, preferences, and admin broadcast.

**Deliverables**
- Tables: `notification_preferences`, `sms_logs`, `push_subscriptions`.
- One Notification class per matrix event; `via()` resolves channels from matrix + preferences; mandatory events cannot be disabled (enforced in code + test).
- Branded dark Markdown mail theme (logo, anti-phishing code, "never share your OTP", freeze-account link).
- `SmsDriver` + `FailoverSmsDriver`: country route from `countries.sms_route` → Twilio Verify → Vonage → voice → email; templates ≤ 160 chars, no links in OTP SMS; log every attempt.
- `PushDriver` (FCM + Web Push/VAPID); payloads hide sensitive amounts on lock screen.
- Queued on `notifications`; 3× backoff then logged.
- Livewire notifications centre + preferences; Filament templates editor, broadcast tool (segment by tier/country/activity), SMS logs + cost report.

**Acceptance / exit gate:** every matrix row has a test asserting its channels; mandatory events reject disabling.

---

## Phase 7 — Trading Engine

**Goal:** spot matching engine, settlements, order book, candles, realtime UI, stop orders, and circuit breaker.

**Deliverables**
- Tables: `markets`, `orders`, `trades`, `candles`; Redis streams `orders:{market}` + sorted-set book cache.
- **Source of truth & recovery (ADR-0003):** MySQL `orders` is authoritative; Redis is a cache. Orders are persisted (status `pending`, funds locked) *before* being pushed to the stream. On start, the engine rebuilds the book from `orders` where status ∈ (open, partially_filled), then resumes the stream from the last acked id. A fill is applied to Redis only after its DB transaction commits. Redis runs with AOF (`appendfsync everysec`).
- **Throughput target (revised):** the master prompt's 500 orders/sec per market is not achievable with one MySQL transaction per fill in PHP. Phase 7 target is **≥ 100 orders/sec/market sustained, p95 acceptance < 150 ms**; measure with `trading:benchmark` and record the result in DECISIONS. If more is needed later, batch several fills per transaction rather than relax ledger rules.
- `trading:engine {market}` long-running process (one per market, kept alive by the host's process manager; hosting TBD, see ADR-0004): strict sequential, price-time priority, self-trade prevention (cancel newest), partial fills, each fill settled in its own transaction via `LedgerService`; emits `TradeExecuted`, `OrderUpdated` over the broadcaster (`private-user.{id}`, `public-market.{symbol}`). Graceful stop without SIGTERM (Windows has none): `trading:stop {market}` sets a Redis flag the engine checks between orders; it exits after finishing the current one.
- Order validation: market status, tick/lot rounding (reject, not silent), min notional, price band, balance lock, `can_trade`, 10 orders/sec/user.
- Order types: Limit (GTC/IOC/FOK/POST_ONLY), Market (base qty or quote, max-slippage guard), Stop-limit (`trading:trigger-stops` daemon).
- Fees: maker/taker per market, 30d-volume tier discounts, charged in received asset → `system_fee`.
- `TRADING_LIQUIDITY_MODE`: `internal` | `market_maker` (platform MM account on the ledger, quoting around external index).
- Candles built from trades in Redis, flushed every minute; `/api/markets/{symbol}/candles`.
- Circuit breaker: > X% move in 1 min → `halted` + admin alert; cancel-all on account freeze.
- Commands: `trading:expire-orders`, `candles:persist`, `trading:benchmark`.
- Livewire Trade screen (chart / order book / recent trades / order form / open orders + history), live updates via the broadcaster (Reverb, or a hosted Pusher-compatible service if the host can't run websockets) with 300ms flash.
- Filament: markets (fees, tick/lot, halt/resume, bands), orders, trades.

**Acceptance / exit gate:** matching-engine property tests pass (asset conservation, no crossed book); crash-recovery test (kill engine mid-stream → restart → book identical to DB, no double fill); `trading:benchmark` ≥ revised target; trade UI live-updates.

> **Human review gate:** engine + settlement reviewed by a person before Phase 8.

---

## Phase 8 — Staking (Earn)

**Goal:** funded yield accrual from the pool, flexible + fixed products, maturity, and admin forecast.

**Deliverables**
- Tables: `staking_products`, `staking_subscriptions`, `staking_accruals`, `staking_redemptions`.
- Yield **must be funded**: every accrual is `system_yield_pool → user.staking`; insufficient pool → product `paused` + critical alert; never mint.
- Flexible: subscribe anytime (spot → staking), daily accrual, redeem with T+1 principal availability. Fixed: lock for `lock_days`, maturity release to spot or auto-renew, early redemption per product rules.
- Daily interest = principal × APR / 365 (bcmath scale 18, truncated to asset precision).
- Jobs: `staking:accrue` (daily 00:05 UTC, idempotent per subscription/date, chunk 500), `staking:mature` (hourly), `staking:process-redemptions` (10 min), `staking:capacity-check` (hourly).
- `MoneyLanguageTest`: forbidden wording ("guaranteed", "risk-free", "no risk") across lang + Blade; product page shows APR (variable label), lock, `yield_source_description`, early-redemption terms, capacity, risk disclosure.
- Livewire Earn (products, subscriptions, accrual history, progress/countdown) + Filament (products, pool funding via maker-checker, liability forecast 30/90 days).

**Acceptance / exit gate:** pool-insufficient path pauses the product; accrual idempotent.

---

## Phase 9 — Copy Trading

**Goal:** leader marketplace, follower mirroring with correct sizing, real-fill stats, stop-loss, and HWM profit share.

**Deliverables**
- Tables: `lead_traders`, `copy_relationships`, `copy_orders`, `lead_trader_stats_daily`, `profit_share_settlements`.
- Leader application (tier 3, ≥ 30 days history, min equity) → admin approval; allowed-markets constraint; suspend → followers auto-paused + notified.
- Mirroring: listener on leader `OrderFilled` (spot, source=user) → `MirrorLeaderOrderJob` per follower (queue `copy`) → sized per mode (fixed per trade | proportional to leader equity) → `copy_orders` records skips with reason (insufficient balance, min notional, paused, slippage).
- **Anti-abuse (ADR-0003):** mirrored orders are **limit IOC at leader fill price ± `max_copy_slippage_pct`** (per relationship, default 1%), never unbounded market orders — the unfilled remainder is skipped with reason `slippage`. A leader's order that matches against any of their own followers' copy orders is blocked by self-trade prevention extended to the leader–follower pair, and raises AML alert `LEADER_FOLLOWER_CROSS`. Markets below a configurable 24h volume cannot be enabled for copy trading.
- Follower settings: allocation (spot → copy wallet), fixed/proportional, max per trade, copy stop-loss %, take-profit %, market filter.
- Stats nightly from real fills only: ROI 7/30/90/all, PnL, max drawdown, win rate, avg hold, trades, Sharpe(30d), AUM, followers; equity curve; "Past performance..." disclaimer.
- Profit share: HWM, settled weekly (Mon 00:30 UTC) + on unfollow; `follower → leader` ledger transfer.
- Jobs: `copy:compute-stats`, `copy:check-stop-loss`, `copy:settle-profit-share`.
- Livewire leaderboard, trader profile (equity curve), follow settings, my copies + Filament applications/leaders/relationships/settlements.

**Acceptance / exit gate:** leader fill mirrors to N followers correctly sized; HWM settlement tested.

---

## Phase 9b — Signals (ADR-0006)

**Goal:** in-house trade signals from two sources — staff analysts publishing from the admin panel, and an automatic indicator engine running on platform candles — with outcomes tracked automatically against live prices and track records that count every result, losses included. No outside providers in v1.

**Why it sits here:** signals need live markets, prices and candles (Phase 7) to track entries, targets and stops, and they reuse the notification matrix (Phase 6) and admin maker-checker (Phase 10 builds the general version; this phase uses a signal-specific review step).

**Deliverables**
- Module `app/Domain/Signals/` (new; scaffolded with the `new-domain-module` skill). Talks to Trading only through its public contracts: market lookup, last price, closed candles.
- Tables:
  - `signal_providers`: id, public_id, type (analyst|indicator), user_id (nullable; the staff analyst), display_name, bio, strategy_key (indicator only, e.g. `ema_cross_20_50`, `rsi_reversal_14`, `macd_cross`), timeframes (json), status (active|paused|retired), timestamps.
  - `signals`: id, public_id, provider_id, market_id, side (buy|sell), timeframe (1h|4h|1d), entry_low, entry_high, stop_loss, confidence (1–5), rationale (text, required), status, outcome (null|target_1|target_2|target_3|stopped|expired_no_entry|cancelled), result_pct DECIMAL(10,6), price_at_publish, entered_at, closed_at, expires_at, created_by, reviewed_by, published_at, cancel_reason, source_candle_open_time (indicator only), timestamps. UNIQUE(provider_id, market_id, timeframe, source_candle_open_time) so the indicator engine is idempotent. Index (status, market_id), (provider_id, closed_at).
  - `signal_targets`: signal_id, sequence (1–3), price, hit_at; UNIQUE(signal_id, sequence).
  - `signal_events`: append-only log of every state change and target/stop hit (signal_id, type, price, payload json, created_at).
  - `signal_provider_stats_daily`: provider_id, date, signals_closed, entered, targets_hit, stopped, expired_no_entry, hit_rate DECIMAL(10,6), avg_result_pct, median_result_pct, best/worst_result_pct; UNIQUE(provider_id, date).
  - `signal_follows`: user_id, provider_id, channels (json); UNIQUE(user_id, provider_id).
- **State machine** (`SignalStatus`): draft → pending_review → published → active (entry zone touched) → closed; published|active → cancelled (with reason, staff only). Illegal transitions throw.
- **Publishing rules** (`PublishSignalAction`), enforced in code and tests:
  - Level ordering — buy: stop < entry_low ≤ entry_high < T1 < T2 < T3; sell: mirrored. Spot only: "sell" means reduce holdings, never short.
  - Entry zone within `signals.max_entry_distance_pct` of the last price at publish; levels respect the market tick size.
  - Rationale required; confidence 1–5; expiry required (default per timeframe: 1h → 24h, 4h → 3d, 1d → 10d).
  - **Immutable once published.** Levels, targets and rationale can't be edited; staff can only cancel with a reason. Cancelling an active signal still counts it in the track record at its current result, so nobody can hide a loser.
  - Analyst signals go through review: author ≠ reviewer (maker-checker), same as other sensitive admin actions.
- **Outcome engine** (`signals:evaluate`, every minute, plus on each closed 1m candle): walks candle high/low since the last check — entry touched → `active`; then targets in order and the stop. If a stop and a target fall inside the same candle, the stop wins (conservative; documented on the methodology page). Unentered signals past `expires_at` close as `expired_no_entry`. `result_pct` is measured from the entry-zone midpoint to the exit level, before fees.
- **Indicator engine** (`signals:generate`, after each candle close for configured timeframes): EMA 20/50 cross, RSI(14) reversal from <30 / >70, MACD(12,26,9) signal-line cross, each filtered by volume and trend. All indicator maths in `Decimal`. Levels from ATR(14): stop 1.5 ATR beyond entry, targets at 1R / 2R / 3R. Auto-published (no review), labelled "Automatic" everywhere, and rate-limited per market.
- **Track records** (`signals:compute-stats`, nightly + on close): per provider, from closed signals only — hit rate = signals reaching T1 or better ÷ signals that entered; expired-without-entry and cancelled shown separately, never dropped. A public methodology page explains every formula.
- **User features:** Signals page (filters, cards with entry/stop/targets and a live price ladder, "Why this signal", confidence, provider record), closed-signal history, provider profiles, follow a provider, "Alert me at entry" (uses price alerts), and "Trade this" which **pre-fills** the order form — the user always sets the amount and confirms. No auto-trading from signals.
- **Admin (Filament):** providers, signal composer with live level validation, review queue, outcomes, stats, and pause/retire for indicator strategies.
- **Notifications** (matrix rows added): new signal from a followed provider (O), target/stop hit on a followed signal (O). Never SMS.
- **Compliance:** feature flag `signals.enabled` (default **false** until legal sign-off, because publishing signals can count as investment advice) and per-country gating via `countries.features_disabled` (`"signals"`). Disclaimer on every signal surface; `MoneyLanguageTest` extends to signal copy ("guaranteed", "risk-free", "sure win" are forbidden).
- **Demo data** (`DemoSignalSeeder`, runs only when `APP_DEMO=true`): seeds three analyst personas and the three indicator strategies, generates ~90 days of simulated candles per market (random walk with drift and volatility regimes), runs the **real** indicator engine and **real** outcome engine over them, and has the analyst personas publish through `PublishSignalAction`. So every demo outcome and track record is produced by the same code as production, wins and losses included, and nothing is hand-typed.

**Jobs/queues:** `signals` queue (default priority); scheduler: `signals:evaluate` (every minute), `signals:generate` (on candle close / every 5 min fallback), `signals:compute-stats` (daily 00:40 UTC), `signals:expire` (every 5 min).

**Acceptance / exit gate:** level-ordering and immutability tests; state-machine tests; outcome engine fixture tests (entry then T1/T2/T3, stop, same-candle stop+target → stopped, expiry without entry); stats recomputed from scratch equal the stored stats; indicator engine deterministic on fixture candles; demo seeder refuses to run when `APP_DEMO=false`; flag off or country-disabled → routes 404 and nav item hidden.

---

## Phase 10 — Referrals, Support, Admin, Compliance

**Goal:** the remaining business modules plus the compliance plumbing (geo-restriction, AML, Travel Rule, consents, audit).

**Deliverables**
- **Referrals:** `referral_code` on users; commission = % of actual trading fees for 12 months, paid daily from `system_fee` (`referrals:pay-commissions`); promo credits locked until a volume threshold.
- **Support:** `support_categories`, `support_tickets`, `support_messages`, `support_attachments`, `canned_responses`; SLA by priority; round-robin assignment; internal notes; CSAT; auto-close; realtime via the broadcaster; `support:sla-check`, `support:auto-close`.
- **Admin:** `settings`, `approval_requests` (maker-checker, requester ≠ approver); Filament resources/pages per master prompt Section 8.13; RBAC roles seeded (`super_admin`, `admin`, `compliance`, `finance`, `support_lead`, `support_agent`, `read_only`); daily finance report (`reports:daily-finance`).
- **Compliance:** `aml_alerts`; configurable AML rules (`aml:scan` every 15 min); geo-restriction (declared residence + KYC document country + MaxMind GeoIP; VPN flag; newly blocked → withdraw-only + notify); `travel_rule_records` (`TravelRuleProvider`, threshold from config); nightly rescreen; sanctions/PEP.
- **Data protection & legal:** `consents` (per doc version + jurisdiction), cookie consent banner, data-processing register in `docs/PRIVACY.md`, versioned legal pages under `resources/legal/{locale}/{jurisdiction?}/`.
- **Audit immutability:** verify the app DB user has no UPDATE/DELETE on `audit_logs` (documented in RUNBOOK).

**Acceptance / exit gate:** SLA + AML rules tested; blocked-country registration denied; Travel Rule data collected above threshold.

---

## Phase 11 — Internationalisation & Extras

**Goal:** multi-locale, multi-fiat, and the remaining user-facing features.

**Deliverables**
- i18n: scaffold `en, fr, es, pt, ar, zh, ru, tr`; `lang/{locale}.json`; `php artisan i18n:missing` reports gaps; `Accept-Language` detection; locale used in emails/SMS; RTL via `dir` + Tailwind logical properties (`ms-/me-/ps-/pe-`).
- Multi-fiat display: any active `fiat_currencies` as display currency; `fx:sync` every 15 min; converted values always labelled estimates.
- Price alerts: `price_alerts` + `PriceAlertMatcher` (Redis sorted sets) checked on each tick; delivered via the notification matrix.
- Push: per-device subscriptions, no sensitive amounts on lock screen (configurable).
- Internal transfers: send by email/UID, ledger journal, 2FA, recipient T1+, counts toward AML limits.
- Statements & tax reports: monthly/yearly PDF + CSV in preferred fiat; `statements:generate`; generic crypto-tax CSV.
- Account recovery: request → re-liveness vs approved selfie → support review → 48h cooling with contact notifications → reset; withdrawals locked 48h after. `recovery:process-cooling`.
- Status page `/status`; per-asset maintenance banners; announcements + help centre (per locale/country).
- PWA: manifest, offline shell, icons.

**Acceptance / exit gate:** `i18n:missing` clean for `en`; RTL renders in `ar`; alert fires within 2s of a matching tick.

---

## Phase 12 — Public API, E2E, Hardening & Deploy

**Goal:** ship the public API, full end-to-end proof, performance targets, and deployment docs.

**Deliverables**
- API v1 (`routes/api.php`): Sanctum tokens (first-party mobile) + HMAC API keys (`X-API-KEY`, `X-TIMESTAMP`, `X-SIGNATURE`, 30s window); public markets/ticker/depth/trades/candles; private read balances/deposits/withdrawals/orders/trades/staking/copy; private write place/cancel order (withdrawal creation never via API key). Cursor pagination; error envelope `{error:{code,message,details}}`; OpenAPI 3 via `dedoc/scramble` at `/docs/api`.
- Dusk E2E: register → KYC T1 (fake) → deposit (fake gateway) → trade → stake → withdraw, including a non-English, non-USD journey.
- Performance pass: order p95 < 150ms to acceptance; matching ≥ 100 orders/sec/market (revised, see Phase 7); dashboard TTFB < 300ms; Lighthouse mobile ≥ 85 perf, ≥ 95 accessibility.
- Final security review recorded in `docs/SECURITY_REVIEW.md`; `composer audit` + `npm audit` clean.
- Deployment docs for the production hosting target. **Hosting is not decided yet** (ADR-0004); candidates are Windows Server and cPanel. Whatever is chosen must run long-lived processes (queue workers, websockets, one trading engine per market) with auto-restart, plus the scheduler every minute.
- `docs/RUNBOOK.md` complete; `docs/HANDOFF.md` complete (what was built, how to run, production env keys, known limitations, go-live checklist).

**Acceptance / exit gate:** full E2E green; Lighthouse targets met; RUNBOOK + HANDOFF complete; `PLATFORM_LIVE_MODE` flip procedure documented and rehearsed.

---

## 6. Testing strategy (per phase)

- Pest unit tests for every Action; feature tests for every HTTP/Livewire flow.
- Every money flow: happy path (exact Decimal string assertions), insufficient funds, idempotent replay, concurrency (10 parallel attempts), ledger-balanced assertion, audit row, notifications.
- Architecture tests: in `App\Domain`, raw queries (`DB::table`, `DB::select`, `DB::statement`, `DB::raw`) are allowed only in Repositories and `LedgerService`; `DB::transaction` is allowed in Actions (ADR-0003). No `float` type in `App\Domain`. A module may only reference another module's `Contracts\*`, `Actions\*`, `DTOs\*`, `Events\*` and `Enums\*` — never its Models or Repositories.
- Concurrency tests use `proc_open` parallel processes (Windows-compatible). CI runs on Windows and Linux.
- `LedgerInvariantTest` on every CI build.
- Webhook fixture tests per provider; matching-engine property tests; Dusk browser smoke tests (Phase 12).
- Factories + seeders for every model; `DemoSeeder` only under `APP_DEMO=true` (faucet → ledger, real engine-generated trades, funded staking products).

## 7. Documentation duties (living)

| Doc | Updated when |
|---|---|
| `docs/ARCHITECTURE.md` | each module is added (module section) |
| `docs/DECISIONS.md` | any non-obvious choice (ADR) |
| `docs/PROGRESS.md` | end of each phase |
| `docs/SECURITY_REVIEW.md` | end of each phase / pre-release |
| `docs/SETUP.md` | new env keys / setup steps |
| `docs/RUNBOOK.md` | new daemon, job, or incident playbook |
| `docs/API.md` | each API surface added |
| `docs/PRIVACY.md` | each data category / sub-processor |
| `CHANGELOG.md` | each phase commit |

## 8. Definition of done (global)

- No `TODO`/`FIXME`/stub/placeholder returns/"implement later" in committed code.
- `composer test && composer analyse && composer lint` and `npm run build` all green.
- All user-facing strings via `__()`; no hardcoded country, currency, timezone, phone, or date format.
- Money columns DECIMAL(36,18); rates DECIMAL(10,6); no float anywhere money is handled.
- Security-review checklist satisfied and recorded for the phase.

## 9. Open operator inputs (blockers to specific gates)

| Input | Blocks | Status |
|---|---|---|
| Home jurisdiction / regulator | **Phase 3 start** (decides KYC tiers/limits, Travel Rule thresholds, and whether Staking and Copy Trading may be offered at all), `PLATFORM_LIVE_MODE`, legal pages | **TBD** (ADR-0001) — must be decided before Phase 3, not at go-live |
| Serve list / restricted-country decisions | Phase 10 geo-restriction content, seed data | Partial (sanctioned jurisdictions blocked by default) |
| Logo SVG + domain | Branding, mailables, legal pages | **TBD** (fallback placeholder until provided) |
| Real provider keys (payments, chain, SMS, KYC, push, FX, custody) | Go-live only — all drivers have Fake for build/test | **TBD** |

## 10. Risk register

| Risk | Impact | Mitigation |
|---|---|---|
| Ledger correctness | Loss of funds / imbalance | Phase 2 built first; invariants in CI; all money via `LedgerService` |
| Matching-engine correctness under load | Bad fills / crossed book | Property tests + benchmark gate in Phase 7; strict sequential stream |
| Provider drift (webhooks/drivers) | Missed deposits/withdrawals | Fixtures + Fake drivers; signature-first ingestion; poller fallback |
| Regulatory / licensing | Cannot legally go live | `PLATFORM_LIVE_MODE=false` default; go-live checklist in HANDOFF |
| Scope size (13 phases) | Schedule overrun | Strict phase gates; each phase independently green + committed |
| Mono/locale/RTL regressions | Broken non-English UX | i18n + RTL tests in Phase 11; Dusk non-English journey in Phase 12 |
| Staking / copy trading treated as securities or investment management | Feature must be removed or licensed separately | Feature flags per country (`countries.features_disabled`); jurisdiction decided before Phase 3 |
| Market-maker mode = platform trading against users | Conflict of interest, disclosure duty | Off by default; MM account and its PnL visible in admin; disclosed in Terms when enabled |
| Binance public API blocked in some regions (HTTP 451, incl. US) | No price feed | CoinGecko fallback tested in CI with a forced-451 fake; feed source shown in admin health |
| Signals treated as investment advice | Feature needs a licence or must stay off in some countries | `signals.enabled` defaults to false; per-country gating; no auto-trading; full track records incl. losses (ADR-0006) |
| Hosting undecided | Some hosts (e.g. shared cPanel) can't run long-lived processes, websockets or Redis | Decide before Phase 7; heartbeat alerts on every long-running process (ADR-0004) |
| Unreviewed autonomous build | Money bugs ship unnoticed | Human review gates after Phase 2 (ledger) and Phase 7 (engine) |
