# Architecture — TradeMax

> Living document. Update as modules are added. Source of truth for the initial design is the master build prompt (`crypto-platform-agent-prompt (2).md`).

## Overview

TradeMax is a custodial crypto exchange & investment platform built on Laravel 12 (PHP 8.3+), MySQL 8, Redis 7, Livewire 3, Filament, and Reverb. Built on Windows; production hosting TBD (ADR-0004). All money movements are recorded as balanced double-entry journals.

## Stack

| Layer | Choice |
|---|---|
| Language / framework | PHP 8.3+, Laravel 12 |
| Database | MySQL 8 (InnoDB, strict mode) |
| Cache / queue / locks | Redis 7 (`phpredis`) |
| Queues | `queue:work` workers (no Horizon, it needs `pcntl`), monitored via Pulse + Filament failed jobs |
| Realtime | Laravel Reverb + Echo (hosted Pusher-compatible fallback if the host can't run websockets) |
| Frontend | Blade + Livewire 3 + Alpine.js + Tailwind 4 |
| Admin panel | Filament on `/admin`, separate guard |
| Money math | `bcmath` via `App\Support\Money\Decimal` |
| Testing | Pest 3, Larastan level 8, Pint, Rector |

## Domain modules

Each module lives in `app/Domain/<Module>/` with `{Models,Actions,Services,Repositories,DTOs,Events,Listeners,Jobs,Enums,Exceptions,Policies}`.

- **Identity** — users, sessions, 2FA, devices, login history
- **Kyc** — tiers, submissions, documents, provider drivers
- **Assets** — assets, networks, asset_networks, price feed
- **Ledger** — wallets, journals, entries, snapshots, system accounts
- **Payments** — deposits, gateways, webhooks, manual receipts, chain verifiers
- **Withdrawals** — requests, whitelist, risk engine, payout drivers
- **Trading** — markets, orders, trades, matching engine, order book cache
- **Staking** — products, subscriptions, accruals, redemptions, yield pool
- **CopyTrading** — lead traders, followers, copy orders, stats, profit share
- **Referrals** — codes, rewards from real trading fees only
- **Notifications** — matrix, channels, templates, preferences, SMS drivers
- **Support** — tickets, messages, categories, SLA
- **Admin** — settings, maker-checker approvals, reports
- **Compliance** — AML rules, alerts, sanctions screening, reports

## Patterns

- Repository + Service + single-purpose Action classes.
- Readonly DTOs; domain Events dispatched to queued Listeners.
- Strategy/driver pattern for every external provider (payments, chain, payout, SMS, KYC, push, FX, travel rule).
- Enum state machines with explicit `canTransitionTo()` maps.
- Cross-module communication via Events only.

## Foundations (Phase 0)

- **Money:** `App\Support\Money\Decimal` is an immutable bcmath value at scale 18. It rejects floats (TypeError) and non-plain numeric strings. Division truncates toward zero; `roundDown` (balances, payouts) and `roundHalfUp` are explicit. `DecimalFormatter` formats for display using only intl's separator symbols, so 18-decimal values never pass through a float.
- **Public ids:** `HasPublicUlid` adds a ULID `public_id` and makes it the route key.
- **UI:** tokens in `resources/css/tokens.css` (dark default, `[data-theme=light|system]`), mapped to Tailwind utilities in `app.css`. Components live in `resources/views/components/ui`, with Alpine behaviour in `resources/js/ui.js`. The layout reads its sidebar from `config/navigation.php`, and items whose route doesn't exist yet render disabled.
- **Guards:** `tests/Arch` enforces strict types everywhere and no float types, casts or float math functions in `App\Domain` and `App\Support\Money`.

## Module sections

### Assets & Ledger (Phase 2)

- **Assets** (`app/Domain/Assets`): `assets`, `networks`, `asset_networks`. Other modules read them through `AssetCatalog` (returns `AssetData`). `HaltAssetWithdrawals` / `ResumeAssetWithdrawals` control the per-asset withdrawal halt.
- **Wallets** are created on first use through the `Wallets` contract (`forUser`, `system`), which returns `WalletRef` handles. Other modules never load the `Wallet` model.
- **Posting:** build a `JournalDTO` (reference, idempotency key, ≥ 2 `EntryDTO`s, `PostedBy`) and call `LedgerService::post()`. It returns `PostedJournal` (`replayed` is true for a known key). Failures: `InsufficientBalance` (nothing written), `IdempotencyConflict`, `InvalidJournal` (caller bug). `JournalPosted` fires after commit.
- **Locking (ADR-0003/0008):** user wallets and checked system wallets are locked in ascending id order; `system_hot` and credited `system_fee` are updated atomically at the end of the transaction.
- **Read side:** `LedgerTotals` (DB aggregates) backs `LedgerInvariants`, snapshots, reconciliation and proof of liabilities. Sums are done by MySQL in DECIMAL.
- **Jobs:** `ledger:snapshot` (daily 00:01), `ledger:reconcile` (hourly), `ledger:invariant-check` (15 min), scheduled in `routes/console.php`.
- **Tests:** `Tests\Support\LedgerAssertions::assertBalanced()` after money tests; `tests/Concurrency` for parallel-process races.

### Identity (Phase 1)

- **Flow:** register (country gate → E.164 phone → strength + breach-checked password → consents) → email link → new-device code (if needed) → SMS code → authenticator app → app. The `app` middleware group enforces it: `auth, verified, device.verified, phone.verified, two-factor.confirmed, account.active`.
- **Auth:** Fortify controllers on our own routes (`routes/auth.php`); `AuthenticateUser` checks credentials, records failures and requires CAPTCHA after repeated failures. `RecordSuccessfulLogin` (on `Login`) records the sign-in, recognises the device and alerts on new devices/IPs.
- **Sensitive changes** are single-purpose actions in `app/Domain/Identity/Actions`. Each verifies step-up codes itself (`StepUpVerifier`: TOTP + emailed OTP), then calls `SecurityEvents::record()`, which applies the 24h withdrawal lock where required, writes the audit row, alerts the user (and the previous contact) and dispatches `SecurityEventOccurred` for other modules.
- **Codes:** `OtpService` issues 6-digit codes stored as HMACs, bound to purpose and destination, valid 10 minutes, 5 guesses, 3 issues per 10 minutes.
- **Cross-module:** countries via `Compliance\Repositories\Contracts\CountryDirectory`; SMS via `Notifications\Contracts\SmsSender`; sessions via `Identity\Repositories\Contracts\SessionStore`.

_(One section per module added as it is built — see `new-domain-module` skill step 10.)_
