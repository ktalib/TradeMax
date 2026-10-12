# MASTER BUILD PROMPT — {{PLATFORM_NAME}} Crypto Exchange & Investment Platform
### For autonomous coding agents (Claude Code, Codex, Cursor Agent, Devin, etc.)

> Replace every `{{PLACEHOLDER}}` before running. Everything else is decided — the agent must not stop to ask questions that this document answers. Where this document is silent, the agent picks the conventional Laravel choice, records it in `docs/DECISIONS.md`, and continues.

---

## 0. MISSION & OPERATING MODE

You are a principal-level Laravel engineer building **{{PLATFORM_NAME}}**, a production-grade custodial crypto platform with: spot trading, crypto deposits/withdrawals (automated + manual), fixed/flexible staking, copy trading, tiered KYC, omni-channel notifications, admin back-office, and support tickets.

### 0.1 Autonomy rules
1. Work **phase by phase** (Section 17). Do not start a phase until the previous phase's acceptance checks pass.
2. After every phase: run `composer test`, `composer analyse`, `composer lint`, `npm run build`. Fix all failures before moving on.
3. Never leave `TODO`, `FIXME`, stub methods, placeholder returns, or "implement later" comments in committed code. If something is genuinely external (API keys, legal text), read it from config/env and document it in `docs/SETUP.md`.
4. Commit after each green phase with Conventional Commits (`feat(trading): add limit order matching`).
5. Maintain these living docs as you go: `docs/ARCHITECTURE.md`, `docs/DECISIONS.md` (ADR-style), `docs/SETUP.md`, `docs/RUNBOOK.md`, `docs/API.md`, `CHANGELOG.md`.
6. If a step fails 3 times with the same error, write the problem and attempted fixes to `docs/BLOCKERS.md`, implement the safest fallback, and continue.
7. Never invent balances, prices, yields, fills, trader statistics, or testimonials. Every number shown to a user must be derived from ledger rows, real fills, or a real price feed.

### 0.2 Output expectations
- A runnable repository: `git clone → cp .env.example .env → make setup → make dev` works on a fresh machine.
- Seeders produce a realistic **demo** environment (clearly flagged `APP_DEMO=true`, never active in production).
- Full test suite (Pest) with ≥ 85% line coverage on `app/Domain/**`.

---

## 1. TECH STACK (fixed — do not substitute)

| Layer | Choice |
|---|---|
| Language / framework | PHP 8.3+, Laravel 12 (latest stable) |
| Database | MySQL 8.0+ (InnoDB, `utf8mb4_0900_ai_ci`), strict mode ON |
| Cache / queue / locks | Redis 7 (`phpredis` extension) |
| Queue dashboard | Laravel Horizon |
| Realtime | Laravel Reverb (WebSockets) + Laravel Echo |
| Frontend | Blade + Livewire 3 + Alpine.js + Tailwind CSS 4 |
| Charts | TradingView Lightweight Charts (price), ApexCharts (dashboards) |
| Admin panel | Filament (latest stable) on `/admin`, separate guard |
| Auth | Laravel Fortify + TOTP 2FA (`pragmarx/google2fa`) + WebAuthn passkeys (optional, behind flag) |
| Roles | `spatie/laravel-permission` |
| Audit | `spatie/laravel-activitylog` + custom immutable `audit_logs` |
| Money math | `bcmath` exclusively (wrapped in `App\Support\Money\Decimal`) |
| Files | S3-compatible private disk (`kyc`, `receipts`, `tickets`), signed temporary URLs only |
| Images | `intervention/image` (re-encode, strip EXIF) |
| QR | `simplesoftwareio/simple-qrcode` |
| Monitoring | Laravel Pulse, Sentry (`sentry/sentry-laravel`) |
| Testing | Pest 3, Larastan (level 8), Laravel Pint, Rector |
| Local env | Laravel Sail (Docker) + `Makefile` |

Default integrations (pluggable via drivers — Section 6):
- Crypto payments: **NOWPayments** (primary), **CoinGate** (secondary).
- On-chain verification: Etherscan/BscScan (EVM), TronGrid (TRON), Blockstream/Mempool.space (BTC).
- SMS / voice OTP: **Twilio Verify** (primary, global), **Vonage** (fallback). Optional per-country routing table (e.g. route a country code to a regional provider) configured in admin, not hardcoded.
- Email: SES or Resend via Laravel Mail; Mailpit locally.
- KYC/identity: **Sumsub** (default) or **Onfido** / **Veriff**, selected by `KYC_DRIVER`. All three are international: government ID from 200+ countries, liveness, face match, proof of address, and built-in sanctions/PEP/adverse-media screening.
- Push: Firebase Cloud Messaging (mobile) + Web Push (VAPID) for browsers.
- FX rates (fiat display only): Open Exchange Rates, with exchangerate.host fallback.
- Price feed: Binance public market-data API (read-only, no account) with CoinGecko fallback.

---

## 2. AGENT RULES FILE — create `CLAUDE.md` (also copy to `AGENTS.md`)

Create this file at repo root verbatim, then obey it for the rest of the build.

```markdown
# Project rules — {{PLATFORM_NAME}}

## Non-negotiables (money)
- NEVER use float/double for any amount, price, rate, or fee. Use `App\Support\Money\Decimal` (bcmath, scale 18).
- DB columns for amounts: DECIMAL(36,18). Prices: DECIMAL(36,18). Percentages/rates: DECIMAL(10,6).
- Balances are NEVER updated directly. All balance changes go through `LedgerService::post()` which writes a balanced double-entry journal (sum of debits == sum of credits per asset) inside one DB transaction.
- Every balance-mutating operation: DB::transaction + `lockForUpdate()` on affected wallet rows, locked in ascending wallet id order (deadlock prevention).
- Every externally-triggered money operation (webhook, job, API call) requires an idempotency key with a UNIQUE index.
- No hard deletes on financial tables. Use status transitions + reversal journal entries.

## Architecture
- Layout: `app/Domain/<Module>/{Models,Actions,Services,Repositories,DTOs,Events,Listeners,Jobs,Enums,Exceptions,Policies}`.
- Controllers/Livewire components are thin: validate (FormRequest / Livewire rules) → call one Action → return.
- Repositories expose interfaces in `Contracts/`, bound in `DomainServiceProvider`.
- State machines: every status column is a backed Enum with an explicit `canTransitionTo()` map; illegal transitions throw.
- Cross-module communication via Events only. No module reaches into another module's tables directly.
- Config over constants: fees, limits, tiers, confirmations, and schedules live in `config/platform.php` and the `settings` table (admin-editable, cached).

## Code standards
- `declare(strict_types=1);` in every PHP file. Final classes by default. Readonly DTOs.
- Larastan level 8 clean. Pint (Laravel preset) clean. No `@phpstan-ignore` without a comment explaining why.
- No N+1: `Model::preventLazyLoading()` in non-production.
- All user-facing strings via `__()`; English default in `lang/en`. No hardcoded country, currency, timezone, phone format, or date format anywhere — always from user profile or config.
- Dates/numbers rendered with the user's locale (`Number::format`, Carbon `isoFormat`); store everything in UTC.

## Security
- All routes behind auth + verified + 2FA-confirmed middleware unless explicitly public.
- Sensitive actions (withdraw, change email/phone/password, add whitelist address, disable 2FA, API key creation) require step-up auth: TOTP + email OTP, and trigger a 24h withdrawal lock where specified.
- Webhooks: verify signature BEFORE parsing business data; reject replays (store provider event id, UNIQUE).
- Files: validate MIME by content, re-encode images, max 10 MB, private disk, signed URLs (5 min).
- Rate-limit every auth, OTP, order, and withdrawal endpoint.
- Log PII-free. Mask emails/phones/addresses in logs.

## Testing
- Every Action has a Pest test. Every money flow has: happy path, insufficient funds, concurrency (parallel attempts), idempotency (replay), and ledger-balanced assertion.
- `LedgerInvariantTest` runs on every CI build: for each asset, SUM(debits) == SUM(credits) and no wallet available balance < 0.

## Workflow
- Small commits, Conventional Commits.
- Update docs/DECISIONS.md for any non-obvious choice.
- Before finishing a task: composer test && composer analyse && composer lint.
```

---

## 3. AGENT SKILLS — create under `.claude/skills/`

Each skill is a folder with a `SKILL.md`. Create all of them in Phase 0 and use them throughout.

### 3.1 `.claude/skills/ledger-posting/SKILL.md`
```markdown
---
name: ledger-posting
description: Use whenever code credits, debits, locks, unlocks or transfers any user or system balance.
---
1. Build a `JournalDTO` with: reference_type, reference_id, idempotency_key, description, and >= 2 `EntryDTO`s.
2. Each entry: wallet_id, asset_id, direction (debit|credit), amount (Decimal, > 0), balance_type (available|locked).
3. Call `LedgerService::post($journal)`. It: begins transaction → locks wallets ordered by id → validates per-asset balance → checks idempotency → inserts journal + entries → updates wallet balance columns → appends wallet_balance_snapshots → commits → dispatches `JournalPosted`.
4. Never touch `wallets.available`/`wallets.locked` anywhere else. A grep for `->update(['available'` outside LedgerService must return zero results.
5. Reversals: post a new journal with mirrored entries and `reverses_journal_id` set. Never edit or delete a posted journal.
6. Test: assert balanced, assert idempotent replay returns same journal, assert concurrent double-spend fails.
```

### 3.2 `.claude/skills/new-domain-module/SKILL.md`
```markdown
---
name: new-domain-module
description: Use when scaffolding any new business module (e.g. Staking, CopyTrading).
---
1. Create app/Domain/<Module>/ with Models, Actions, Services, Repositories/Contracts, DTOs, Events, Listeners, Jobs, Enums, Exceptions, Policies.
2. Migration(s) with FKs, indexes on every FK + every status + created_at, UNIQUE on idempotency keys.
3. Status Enum with transition map.
4. Repository interface + Eloquent implementation, bound in DomainServiceProvider.
5. Actions (one public `handle()` each), wrapped in DB::transaction when they write.
6. Policy + permission names registered in PermissionSeeder.
7. Filament resource for admin.
8. Livewire user pages using design-system components only.
9. Pest tests: unit (Actions), feature (HTTP/Livewire), and invariants.
10. Add module section to docs/ARCHITECTURE.md.
```

### 3.3 `.claude/skills/payment-gateway-driver/SKILL.md`
```markdown
---
name: payment-gateway-driver
description: Use when adding or modifying an automated crypto payment provider.
---
1. Implement `App\Domain\Payments\Contracts\PaymentGateway`: createDepositAddress(), createInvoice(), verifyWebhook(Request): bool, parseWebhook(Request): WebhookDTO, getStatus(string $providerRef), supportedAssets().
2. Register in config/payments.php drivers map; select via PAYMENT_GATEWAY env.
3. Webhook controller: verify signature first → store raw payload in payment_webhooks (UNIQUE provider+event_id) → dispatch ProcessPaymentWebhook job → return 200 fast.
4. Job: map provider status → DepositStatus; credit only on `confirmed` with required confirmations; idempotent by provider tx id.
5. Tests with recorded fixture payloads (tests/Fixtures/<provider>/*.json) incl. bad signature, replay, partial payment, overpayment, expired.
```

### 3.4 `.claude/skills/ui-component/SKILL.md`
```markdown
---
name: ui-component
description: Use when building any user-facing screen or component.
---
1. Use only tokens from Section 7 (CSS variables / Tailwind theme). No raw hex in Blade.
2. Reuse `resources/views/components/ui/*` (button, input, select, card, badge, table, modal, tabs, stat, empty-state, skeleton, toast, amount, asset-icon, pnl).
3. Every screen ships: loading skeleton, empty state, error state, mobile layout (≥ 360px), keyboard focus states, aria labels.
4. Numbers: tabular-nums, right-aligned in tables, formatted with <x-ui.amount> (asset precision aware). Gains `text-up`, losses `text-down`, never color alone — add ▲/▼ or +/− sign.
5. Realtime values update via Echo listeners with a 300ms flash (bg-up/10 or bg-down/10).
6. Verify at 360, 768, 1280, 1920 widths before marking done.
```

### 3.5 `.claude/skills/write-money-tests/SKILL.md`
```markdown
---
name: write-money-tests
description: Use when writing tests for any flow that moves funds.
---
Required cases for every money flow:
- happy path with exact Decimal assertions (string compare, never floats)
- insufficient balance → domain exception, no journal written
- idempotency: same key twice → one journal
- concurrency: run 10 parallel attempts (pcntl / parallel processes or Laravel's ParallelTesting) → balance never negative
- ledger balanced after test (LedgerAssertions::assertBalanced())
- audit_log row exists with actor, action, before/after
- correct notifications dispatched (Notification::fake())
```

### 3.6 `.claude/skills/security-review/SKILL.md`
```markdown
---
name: security-review
description: Use at the end of every phase and before any release.
---
Checklist (write results to docs/SECURITY_REVIEW.md):
- Mass assignment: every model uses $fillable, no $guarded = [].
- Authorization: every controller/Livewire action calls a Policy or Gate.
- IDOR: route model binding scoped to auth user where applicable.
- CSRF on all forms; webhooks excluded and signature-verified.
- Rate limits on login, register, OTP, password reset, orders, withdrawals, ticket creation.
- Uploads: MIME sniffed, re-encoded, private disk, size cap.
- Secrets only from env; .env.example has no real values.
- Headers: CSP, HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy (middleware SecurityHeaders).
- Dependencies: composer audit && npm audit with zero high/critical.
- Run the full LedgerInvariantTest.
```

### 3.7 `.claude/skills/migration/SKILL.md`
```markdown
---
name: migration
description: Use when creating or altering database tables.
---
- Amounts DECIMAL(36,18) unsigned where negative impossible; rates DECIMAL(10,6).
- ULIDs (`ulid('public_id')`) for any id exposed in URLs/APIs; bigint auto-increment internally.
- Index: every FK, every status, (user_id, created_at), and any column used in WHERE by a scheduled job.
- UNIQUE on idempotency_key, provider tx ids, tx_hash+network, receipt sha256.
- Timestamps in UTC; display converted to the user's timezone (auto-detected from browser at signup, editable).
- Every migration has a working down() method.
```

### 3.8 Hooks — `.claude/settings.json`
```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          { "type": "command", "command": "vendor/bin/pint --dirty -q || true" }
        ]
      }
    ],
    "Stop": [
      {
        "hooks": [
          { "type": "command", "command": "composer test -- --parallel --stop-on-failure" }
        ]
      }
    ]
  }
}
```

---

## 4. DIRECTORY ARCHITECTURE

```
app/
  Domain/
    Identity/        (users, sessions, 2FA, devices, login history)
    Kyc/             (tiers, submissions, documents, provider drivers)
    Assets/          (assets, networks, asset_networks, price feed)
    Ledger/          (wallets, journals, entries, snapshots, system accounts)
    Payments/        (deposits, gateways, webhooks, manual receipts, chain verifiers)
    Withdrawals/     (requests, whitelist, risk engine, payout drivers)
    Trading/         (markets, orders, trades, matching engine, order book cache)
    Staking/         (products, subscriptions, accruals, redemptions, yield pool)
    CopyTrading/     (lead traders, followers, copy orders, stats, profit share)
    Referrals/       (codes, rewards — commission from real trading fees only)
    Notifications/   (matrix, channels, templates, preferences, SMS drivers)
    Support/         (tickets, messages, categories, SLA)
    Admin/           (settings, maker-checker approvals, reports)
    Compliance/      (AML rules, alerts, sanctions screening hooks, reports)
  Http/  (thin controllers, FormRequests, middleware)
  Livewire/  (user UI, grouped by module)
  Filament/  (admin resources, pages, widgets)
  Support/
    Money/Decimal.php
    Concerns/HasPublicUlid.php
config/platform.php, config/payments.php, config/kyc.php, config/sms.php, config/trading.php
routes/web.php, routes/api.php, routes/webhooks.php, routes/channels.php, routes/console.php
```

Patterns: Repository + Service + single-purpose Action classes; DTOs (readonly); Domain Events → queued Listeners; Strategy/driver pattern for every external provider; Enum state machines.

---

## 5. DATABASE SCHEMA (create all; adjust only to add, never to weaken)

**Identity**
- `users`: id, public_id (ulid), name, email (unique), phone (unique, E.164), email_verified_at, phone_verified_at, password, two_factor_secret (encrypted), two_factor_confirmed_at, kyc_tier (tinyint, default 0), status (active|frozen|suspended|closed), date_of_birth (encrypted), nationality (ISO 3166-1 alpha-2), residence_country (ISO alpha-2), timezone (IANA, auto-detected), locale (BCP-47), preferred_fiat (ISO 4217, default USD), theme, referral_code (unique), referred_by_id, withdrawal_locked_until, last_login_at, last_login_ip, timestamps.
- `user_devices`: id, user_id, fingerprint_hash, user_agent, ip, first_seen_at, last_seen_at, trusted_at.
- `login_attempts`: id, user_id nullable, email, ip, success, reason, created_at.
- `api_keys`: id, user_id, name, key_prefix, key_hash, permissions (json: read|trade), ip_whitelist (json), last_used_at, revoked_at.

**KYC**
- `kyc_tiers`: id, level (0–3), name, daily_withdraw_limit_usd, monthly_withdraw_limit_usd, daily_deposit_limit_usd, can_trade, can_stake, can_copy_trade, can_lead_trade, requirements (json).
- `kyc_submissions`: id, public_id, user_id, target_tier, status (draft|submitted|provider_checking|manual_review|approved|rejected|resubmit_required), provider, provider_ref, risk_score, rejection_reason, reviewed_by, reviewed_at, timestamps.
- `kyc_documents`: id, submission_id, type (passport|national_id|drivers_license|residence_permit|selfie|liveness_video|proof_of_address|source_of_funds|other), issuing_country (ISO alpha-2), document_number_hash (sha256, for duplicate-identity detection — never store the raw number), expires_on, path, sha256, mime, status, provider_result (json, encrypted), timestamps.

**Assets & Ledger**
- `assets`: id, symbol (BTC, ETH, USDT…), name, precision, display_precision, icon_path, is_fiat, is_active, sort.
- `networks`: id, code (BTC, ERC20, BEP20, TRC20, SOL…), name, explorer_tx_url, required_confirmations, is_active.
- `asset_networks`: id, asset_id, network_id, contract_address, deposit_enabled, withdraw_enabled, min_deposit, min_withdraw, withdraw_fee, platform_deposit_address (for manual flow), memo_required.
- `wallets`: id, user_id nullable (null = system account), asset_id, type (spot|staking|copy|system_fee|system_yield_pool|system_hot|system_cold|system_suspense), available DECIMAL(36,18) default 0, locked DECIMAL(36,18) default 0, version (optimistic counter), UNIQUE(user_id, asset_id, type).
- `journals`: id, public_id, reference_type, reference_id, idempotency_key (UNIQUE), description, reverses_journal_id, posted_by (user/admin/system), created_at.
- `journal_entries`: id, journal_id, wallet_id, asset_id, direction (debit|credit), balance_type (available|locked), amount, balance_after, created_at. Index (wallet_id, created_at).
- `wallet_balance_snapshots`: id, wallet_id, available, locked, snapshot_date (daily), UNIQUE(wallet_id, snapshot_date).

**Payments / Deposits**
- `deposits`: id, public_id, user_id, asset_id, network_id, method (gateway|manual), amount_expected, amount_received, provider, provider_payment_id (UNIQUE nullable), tx_hash, UNIQUE(network_id, tx_hash), to_address, confirmations, status (pending|awaiting_confirmations|verifying|manual_review|confirmed|credited|rejected|expired), journal_id, rejection_reason, reviewed_by, credited_at, timestamps.
- `deposit_receipts`: id, deposit_id, path, sha256 (UNIQUE), perceptual_hash, mime, size, scan_status (pending|clean|infected), timestamps.
- `user_deposit_addresses`: id, user_id, asset_network_id, address, memo, provider, UNIQUE(asset_network_id, address).
- `payment_webhooks`: id, provider, event_id, UNIQUE(provider, event_id), signature_valid, payload (json), processed_at, error.

**Withdrawals**
- `withdrawal_addresses`: id, user_id, asset_network_id, label, address, memo, verified_at, usable_after (verified_at + 24h), UNIQUE(user_id, asset_network_id, address).
- `withdrawals`: id, public_id, user_id, asset_network_id, address, memo, amount, fee, net_amount, status (requested|pending_2fa|risk_review|pending_approval|approved|processing|broadcast|completed|rejected|cancelled|failed), risk_score, risk_flags (json), tx_hash, approved_by, second_approved_by, journal_id, idempotency_key UNIQUE, timestamps.

**Trading**
- `markets`: id, symbol (BTC_USDT), base_asset_id, quote_asset_id, tick_size, lot_size, min_notional, maker_fee_rate, taker_fee_rate, status (active|halted|post_only|delisted), price_band_pct.
- `orders`: id, public_id, user_id, market_id, side (buy|sell), type (limit|market|stop_limit), time_in_force (GTC|IOC|FOK|POST_ONLY), price, stop_price, quantity, quote_quantity (market buy by quote), filled_quantity, filled_quote, avg_fill_price, fee_paid, status (pending|open|partially_filled|filled|cancelled|rejected|expired|triggered), source (user|copy|api), copy_parent_order_id, client_order_id, UNIQUE(user_id, client_order_id), locked_amount, timestamps. Index (market_id, status, side, price, created_at).
- `trades`: id, public_id, market_id, maker_order_id, taker_order_id, maker_user_id, taker_user_id, price, quantity, quote_quantity, maker_fee, taker_fee, taker_side, journal_id, created_at.
- `candles`: market_id, interval (1m,5m,15m,1h,4h,1d), open_time, o,h,l,c, volume, quote_volume, PK(market_id, interval, open_time).

**Staking**
- `staking_products`: id, asset_id, type (flexible|fixed), name, apr DECIMAL(10,6), apr_is_variable bool, lock_days, min_amount, max_amount_per_user, total_capacity, subscribed_total, early_redemption_penalty (forfeit_accrued|percent), penalty_rate, yield_source_description (required text, shown to users), status (draft|active|sold_out|paused|closed), starts_at, ends_at.
- `staking_subscriptions`: id, public_id, user_id, product_id, principal, apr_at_subscription, accrued_total, status (active|matured|redeeming|redeemed|early_redeemed), subscribed_at, matures_at, redeemed_at, auto_renew.
- `staking_accruals`: id, subscription_id, accrual_date, amount, journal_id, UNIQUE(subscription_id, accrual_date).
- `staking_redemptions`: id, subscription_id, type (flexible|maturity|early), principal, interest, penalty, available_at (T+1 for flexible), status, journal_id.

**Copy Trading**
- `lead_traders`: id, user_id UNIQUE, display_name, bio, status (applied|active|paused|suspended), profit_share_rate (max 0.20), min_copy_amount, max_followers, markets_allowed (json), approved_by.
- `copy_relationships`: id, follower_id, lead_trader_id, allocated_amount, mode (fixed_amount|proportional), fixed_amount_per_trade, max_per_trade, copy_stop_loss_pct, take_profit_pct, high_water_mark, status (active|paused|stopped), started_at, stopped_at, UNIQUE active per pair.
- `copy_orders`: id, relationship_id, leader_order_id, follower_order_id, status (created|skipped|failed), skip_reason.
- `lead_trader_stats_daily`: lead_trader_id, date, roi, pnl, aum, followers, win_rate, max_drawdown, trades_count, sharpe_30d — computed from real fills only.
- `profit_share_settlements`: id, relationship_id, period_start, period_end, profit, share_amount, journal_id.

**Notifications, Support, Admin, Compliance**
- `notification_preferences`: user_id, event_key, channel (mail|sms|database|push), enabled. Mandatory events cannot be disabled (enforced in code).
- `sms_logs`: id, user_id, phone_masked, driver, template_key, status, provider_message_id, cost, error, created_at.
- `support_categories`, `support_tickets` (public_id, user_id, category_id, subject, priority low|normal|high|urgent, status open|awaiting_agent|awaiting_user|on_hold|resolved|closed, assigned_to, sla_due_at, first_response_at, resolved_at, related_type/related_id for deposit/withdrawal/order), `support_messages` (ticket_id, author_type, author_id, body, is_internal_note), `support_attachments`, `canned_responses`.
- `settings`: key, value (json), group, updated_by. Cached.
- `approval_requests` (maker-checker): id, type (balance_adjustment|withdrawal_large|kyc_override|fee_change|asset_listing), payload (json), requested_by, approved_by, status, reason. Requester ≠ approver enforced.
- `audit_logs`: id, actor_type, actor_id, action, subject_type, subject_id, before (json), after (json), ip, user_agent, created_at. Append-only (DB user has no UPDATE/DELETE grant on this table — document in RUNBOOK).
- `aml_alerts`: id, user_id, rule_code, severity, details (json), status (open|investigating|cleared|escalated|reported), assigned_to.
- `price_ticks` (cache-backed, persisted every minute): asset_id, price_usd, source, captured_at.

**International, alerts & extras**
- `countries`: code (ISO alpha-2, PK), name, status (allowed|restricted|blocked), max_kyc_tier, required_documents (json), proof_of_address_required, risk_level (low|medium|high), features_disabled (json, e.g. ["staking","copy_trading"]), sms_route (nullable driver override), notes. Admin-editable; seeded from {{RESTRICTED_COUNTRIES}}.
- `fiat_currencies`: code (ISO 4217), symbol, decimals, is_active. `fx_rates`: base (USD), quote, rate, source, captured_at.
- `price_alerts`: id, user_id, market_id, condition (above|below|pct_change_24h), target, channels (json), repeat (once|every_time), triggered_at, status (active|triggered|cancelled).
- `push_subscriptions`: id, user_id, type (fcm|webpush), token/endpoint, keys (json, encrypted), device_label, last_used_at.
- `travel_rule_records`: id, transfer_type (deposit|withdrawal|internal), transfer_id, originator (json, encrypted), beneficiary (json, encrypted), counterparty_vasp, provider_ref, status (not_required|pending|exchanged|failed).
- `internal_transfers`: id, public_id, from_user_id, to_user_id, asset_id, amount, memo, journal_id, idempotency_key UNIQUE, created_at.
- `account_recovery_requests`: id, user_id, type (lost_2fa|lost_phone|lost_email), status (submitted|liveness_pending|review|approved|rejected), liveness_ref, reviewed_by, cooling_until, timestamps.
- `consents`: id, user_id, document (terms|privacy|risk_disclosure|marketing|cookies), version, jurisdiction, accepted_at, ip.
- `statements`: id, user_id, period (monthly|yearly), year, month, path, generated_at.

---

## 6. EXTERNAL DRIVER CONTRACTS

All providers are swappable via env. Each has a `Fake` driver used in tests and demo mode.

```php
interface PaymentGateway { /* see skill payment-gateway-driver */ }
interface ChainVerifier {
    public function supports(Network $network): bool;
    public function getTransaction(string $txHash, AssetNetwork $an): ?ChainTxDTO; // to, from, amount, confirmations, token contract, block time, status
}
interface PayoutDriver { public function send(Withdrawal $w): PayoutResultDTO; public function status(string $ref): PayoutStatus; }
interface SmsDriver { public function send(string $e164, string $message, SmsRoute $route): SmsResultDTO; } // route: otp|transactional|marketing
interface KycProvider {
    public function createApplicant(User $u): string;                  // provider applicant id
    public function accessToken(User $u, int $targetTier): string;     // for embedded web/mobile SDK
    public function verifyWebhook(Request $r): bool;
    public function parseResult(Request $r): KycResultDTO;             // decision, reasons, extracted name/DOB/nationality/doc number, sanctions/PEP hits
    public function rescreen(User $u): ScreeningResultDTO;
}
interface PriceFeed { public function ticker(string $symbol): Decimal; public function klines(...): array; }
interface PushDriver { public function send(PushSubscription $s, PushMessageDTO $m): bool; }
interface FxRateProvider { public function rates(string $base = 'USD'): array; }
interface TravelRuleProvider { public function required(TransferDTO $t): bool; public function exchange(TransferDTO $t): TravelRuleResultDTO; } // drivers: notabene|sumsub|null
```

KYC uses the provider's embedded SDK (web + mobile) so the user picks country → document type → capture → liveness in one flow; the provider decides which document types are valid for that country. Results arrive by signed webhook.
SMS failover: `FailoverSmsDriver` tries the country route from `countries.sms_route` if set, else Twilio Verify → Vonage; logs every attempt to `sms_logs`; OTP falls back to voice call, then email, if SMS fails twice. Users in countries with poor SMS delivery can use TOTP/passkey only.
Payout drivers: `ManualPayoutDriver` (default — finance broadcasts from custody and pastes tx hash), `GatewayPayoutDriver` (NOWPayments mass payouts). Never store private keys in the app.

---

## 7. DESIGN SYSTEM (dark-first, premium)

### 7.1 Color tokens — `resources/css/tokens.css`
```css
:root {
  /* Surfaces */
  --bg:            #0A0D14;   /* app background */
  --surface:       #111621;   /* cards, panels */
  --surface-2:     #171D2B;   /* nested cards, table header, inputs */
  --surface-3:     #1F2738;   /* hover, active rows */
  --border:        #252E42;
  --border-strong: #34405A;

  /* Text */
  --text:          #E8ECF4;   /* primary */
  --text-2:        #A3ADC2;   /* secondary */
  --text-3:        #6B7590;   /* muted, labels */
  --text-inverse:  #0A0D14;

  /* Brand */
  --brand:         #4F7CFF;   /* primary actions, links, focus */
  --brand-hover:   #6A90FF;
  --brand-soft:    rgba(79,124,255,.12);
  --accent:        #00E0B8;   /* highlights, staking, rewards */
  --accent-soft:   rgba(0,224,184,.12);

  /* Market semantics */
  --up:            #16C47F;   /* buy, gains */
  --up-soft:       rgba(22,196,127,.12);
  --down:          #F2495C;   /* sell, losses */
  --down-soft:     rgba(242,73,92,.12);

  /* Status */
  --warning:       #F5B021;
  --warning-soft:  rgba(245,176,33,.12);
  --info:          #38BDF8;
  --info-soft:     rgba(56,189,248,.12);
  --danger:        #EF4444;

  /* Effects */
  --ring:          0 0 0 3px rgba(79,124,255,.35);
  --shadow-card:   0 1px 0 rgba(255,255,255,.03) inset, 0 8px 24px rgba(0,0,0,.35);
  --gradient-hero: radial-gradient(1200px 600px at 10% -10%, rgba(79,124,255,.18), transparent 60%),
                   radial-gradient(800px 400px at 90% 0%, rgba(0,224,184,.10), transparent 60%);
  --radius-sm: 8px; --radius: 12px; --radius-lg: 16px; --radius-xl: 24px;
}

/* Optional light theme (toggle stored per user) */
:root[data-theme="light"] {
  --bg:#F6F8FC; --surface:#FFFFFF; --surface-2:#F1F4FA; --surface-3:#E8EDF6;
  --border:#E1E6F0; --border-strong:#CBD3E1;
  --text:#0E1422; --text-2:#475069; --text-3:#7A849C; --text-inverse:#FFFFFF;
  --up:#0EA66B; --down:#E03149;
}
```

### 7.2 Tailwind theme mapping (`resources/css/app.css`, Tailwind 4 `@theme`)
```css
@import "tailwindcss";
@import "./tokens.css";
@theme {
  --color-bg: var(--bg); --color-surface: var(--surface); --color-surface-2: var(--surface-2); --color-surface-3: var(--surface-3);
  --color-border: var(--border); --color-border-strong: var(--border-strong);
  --color-fg: var(--text); --color-fg-2: var(--text-2); --color-fg-3: var(--text-3);
  --color-brand: var(--brand); --color-accent: var(--accent);
  --color-up: var(--up); --color-down: var(--down);
  --color-warning: var(--warning); --color-info: var(--info); --color-danger: var(--danger);
  --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, monospace;
}
body { @apply bg-bg text-fg font-sans antialiased; font-feature-settings: "cv11","ss01"; }
.num { font-variant-numeric: tabular-nums; }
```

### 7.3 Typography
- Inter (UI), JetBrains Mono (addresses, tx hashes, order book). Self-host via `@fontsource`.
- Scale: 12 / 13 / 14 (base) / 16 / 20 / 24 / 32 / 40. Weights 400, 500, 600, 700.
- Order book / tables: 13px mono, tabular numbers, right-aligned.

### 7.4 Layout
- Desktop: fixed 248px left sidebar (collapsible to 72px icon rail), 64px top bar (search, asset ticker marquee, notifications bell, theme toggle, profile menu).
- Mobile (< 768px): sidebar hidden, **bottom tab bar** (Home, Markets, Trade, Earn, Wallet), sticky Buy/Sell bar on trade screen, sheets instead of modals.
- Content max-width 1440px, 24px gutters desktop / 16px mobile, 8px spacing grid.
- Trade screen grid (≥1280px): chart (span 8) | order book (span 2) | recent trades (span 2) ; below: order form (span 4) | open orders/history tabs (span 8). Tablet: chart full width, book + form side by side. Mobile: tabs (Chart / Book / Trades) + bottom sheet order form.

### 7.5 Components (`resources/views/components/ui/`)
button (primary|secondary|ghost|danger|buy|sell; sm|md|lg; loading state), input (with prefix/suffix, max button), select, segmented-control, card, stat-card (value, delta, sparkline), badge (status colors), table (sticky header, sortable, responsive → stacked cards on mobile), tabs, modal/sheet, toast, tooltip, skeleton, empty-state (icon + CTA), amount (precision-aware), pnl, asset-icon, address (mono, truncate middle, copy button, QR), stepper (KYC), progress (staking maturity), countdown, otp-input (6 boxes, paste support).

### 7.6 Motion & accessibility
- 150ms ease-out transitions; price flash 300ms; respect `prefers-reduced-motion`.
- WCAG 2.2 AA contrast; visible focus ring `--ring`; every icon button has `aria-label`; never rely on color alone for up/down.

### 7.7 Screens to build (user)
Landing (marketing, live tickers from real feed), Register/Login/2FA/Forgot, Onboarding checklist, Dashboard (total balance in USD + user's preferred fiat (any active ISO 4217 currency, via `fx_rates`), allocation donut, 24h PnL, recent activity, KYC banner), Markets (search, favorites, 24h change, volume, sparklines), Trade, Wallet (balances by asset, deposit, withdraw, transfer spot↔staking↔copy, history with filters + CSV export), Deposit (gateway / manual tabs), Withdraw (whitelist picker, fee preview, OTP), Earn (staking products, my subscriptions, accrual history), Copy Trading (leaderboard, trader profile with equity curve, follow settings, my copies), KYC centre, Security (2FA, devices, sessions, API keys, anti-phishing code shown in every email), Notifications centre + preferences, Price alerts, Statements & tax reports, Send to user (internal transfer), Account recovery, Referral, Support (tickets), Settings (language, timezone, display currency, theme). Language switcher in top bar and footer; RTL layout supported via `dir` attribute and Tailwind logical properties (`ms-/me-/ps-/pe-`) so Arabic/Hebrew/Urdu can be added without rework.

---

## 8. MODULE SPECS

### 8.1 Identity & Security
- Registration: country of residence first (blocked countries stop here with an explanation; restricted countries see which features are unavailable) → email + phone (international input with country picker, `libphonenumber` validation, E.164 storage) + password (min 10 chars, zxcvbn score ≥ 3, HIBP k-anonymity check) + optional referral code. Email verification link + SMS OTP for phone.
- 2FA mandatory before first withdrawal; prompted at signup.
- New device/IP login → email alert + require email OTP. Trusted devices for 30 days.
- Account freeze self-service ("I've been hacked" button in emails) → freezes withdrawals and API keys immediately.
- Anti-phishing code: user-set phrase rendered in every email header.
- Session list with revoke; auto logout after 30 min idle (configurable).
- Changing email/phone/password or disabling 2FA → `withdrawal_locked_until = now()+24h` + notifications to old and new contact.
- Account recovery (lost 2FA / phone / email): request → KYC provider re-liveness matched against the approved selfie → support review → 48h cooling period with notifications to all known contacts → reset. Withdrawals locked for 48h after recovery.
- Passkeys (WebAuthn) available as a 2FA method; SMS is never the only second factor for withdrawals.

### 8.2 KYC (tiers enforced by middleware `kyc.tier:N` and Policies)
| Tier | Requirements | Daily withdraw | Features |
|---|---|---|---|
| 0 | Email verified + country of residence | 0 | View only, deposit disabled |
| 1 | Phone verified + full legal name, DOB, nationality, residential address (self-declared) + sanctions/PEP screen | {{T1_LIMIT_USD}} | Deposit, trade, flexible staking |
| 2 | Government photo ID (passport, national ID, driver's licence, or residence permit — accepted types per country from the provider) + liveness + face match | {{T2_LIMIT_USD}} | + withdrawals above T1, fixed staking, copy trading (follower) |
| 3 | Proof of address (utility bill / bank statement ≤ 3 months, any country) + source-of-funds questionnaire with optional document | {{T3_LIMIT_USD}} | + lead trader, higher limits, API trading |

Country rules (from `countries` table) override the defaults: a country can require T2 before any deposit, cap the maximum tier, require proof of address at T2, or disable features. Users whose `residence_country` is `blocked` cannot register; if a user's nationality or document country is sanctioned, the submission goes to `manual_review` regardless of score.

Flow: user opens KYC centre → embedded provider SDK (country → document → capture → liveness) → provider webhook → auto-approve if decision is green, extracted name/DOB match the profile, document not expired, `document_number_hash` not already used by another account, and no sanctions/PEP hit; else `manual_review` in admin → approve/reject with reason → notify. Document expiry is tracked; 30 days before expiry the user is asked to re-verify, and on expiry the tier drops to 1 until refreshed. Documents encrypted at rest, retained per `KYC_RETENTION_YEARS`. Limits tracked in rolling 24h / 30d windows in USD equivalent at time of request.

### 8.3 Ledger (core)
- System wallets per asset: `system_fee`, `system_yield_pool`, `system_hot`, `system_cold`, `system_suspense`.
- Deposit credit: debit `system_hot` (on-chain asset) / credit user `spot.available`.
- Order placement: move `available → locked` on user wallet (same wallet, two balance types).
- Trade settlement: one journal per trade covering both parties + fees to `system_fee`.
- Daily reconciliation job: sum of all user liabilities per asset vs. custody balances entered by finance (or read from gateway API) → mismatch > tolerance raises critical alert and auto-halts withdrawals for that asset.
- Proof-of-liabilities export (Merkle tree of hashed user balances) — admin can publish root; users verify their leaf in Wallet page.

### 8.4 Deposits — Rail A: automated gateway
1. User picks asset + network → `CreateDepositAddressAction` returns persistent address (or invoice) + QR + memo warning + min deposit + confirmations needed.
2. Gateway IPN → `routes/webhooks.php` `POST /webhooks/payments/{provider}` (no CSRF, IP allowlist optional) → signature check (NOWPayments: HMAC-SHA512 of key-sorted JSON with IPN secret, header `x-nowpayments-sig`) → store raw → 200.
3. `ProcessPaymentWebhook` job: idempotent upsert of deposit by provider payment id; status mapping; on `finished/confirmed` with confirmations ≥ network requirement → `CreditDepositAction` (ledger) → notify.
4. Partial payment → credit actual received amount, flag. Overpayment → credit actual. Wrong asset/network → `manual_review`.
5. Fallback poller job every 5 min checks pending deposits older than 15 min via `getStatus()` (covers missed webhooks).

### 8.5 Deposits — Rail B: manual wallet transfer with verification pipeline
1. User selects asset + network → shown platform address (`asset_networks.platform_deposit_address`), QR, memo.
2. User submits: amount, tx hash, sending address (optional), receipt screenshot.
3. Pipeline (`VerifyManualDepositJob`, chained):
   a. **Format check**: tx hash regex per network (EVM `^0x[a-fA-F0-9]{64}$`, TRON 64 hex, BTC 64 hex).
   b. **Uniqueness**: UNIQUE(network_id, tx_hash) — duplicates rejected and AML alert `DUPLICATE_TX_CLAIM` raised.
   c. **Receipt integrity**: sha256 UNIQUE (exact duplicate), perceptual hash distance < threshold vs. existing receipts → flag; re-encode image, strip EXIF; ClamAV scan (`scan_status`).
   d. **On-chain verification** via `ChainVerifier`: tx exists, status success, destination == platform address (case-normalised, checksum for EVM), token contract matches asset, amount ≥ claimed − tolerance, block time after deposit request created − 24h, confirmations ≥ required.
   e. All pass → auto-credit. Any soft fail → `manual_review` with machine-readable reasons shown to admin. Hard fail (wrong address, tx not found after 2h of retries, duplicate) → `rejected` with user-visible reason.
4. Retry schedule for unconfirmed tx: 1, 3, 5, 10, 20, 30, 60 min, then hourly up to 24h.
5. Note in UI: the receipt image is supporting evidence only; crediting is based on on-chain verification.

### 8.6 Withdrawals
- Whitelist-only by default (setting can allow one-off addresses for tier 3). New whitelist entry: email confirm link + TOTP; usable after 24h.
- Request: amount + address → fee preview → TOTP + email OTP → `requested` → funds moved `available → locked`.
- Risk engine (`WithdrawalRiskScorer`) adds points for: new device, withdrawal within 24h of security change (blocked outright), first withdrawal, amount > 80% of balance, deposit-then-withdraw within 1h with no trading, destination on internal blocklist, velocity anomalies, AML open alerts. Score ≥ threshold → `risk_review`.
- Auto-approve if: score low, amount ≤ `auto_approve_limit_usd`, hot wallet sufficient. Above `dual_approval_limit_usd` → two different finance admins (maker-checker).
- Processing via PayoutDriver; tx hash recorded; status polled; on failure → unlock funds via reversal journal + notify.
- User can cancel while `requested/pending_approval`.

### 8.7 Spot trading engine
- **Markets**: seeded BTC_USDT, ETH_USDT, BNB_USDT, SOL_USDT, TRX_USDT, ETH_BTC, USDC_USDT. Crypto-only quote assets (USDT/USDC/BTC); fiat values are display-only via `fx_rates`. Fiat pairs are out of scope unless a licensed on-ramp is added (Section 8.15).
- **Order validation**: market status, tick/lot size rounding (reject, don't silently round), min notional, price band (± `price_band_pct` from last price / index), balance lock, KYC `can_trade`, rate limits (10 orders/sec/user).
- **Matching**: one long-running worker per market (`php artisan trading:engine {market}`, Supervisor-managed), consuming a Redis stream `orders:{market}` → strict sequential processing → price-time priority → self-trade prevention (cancel newest) → partial fills → each fill settled in its own DB transaction via LedgerService → events `TradeExecuted`, `OrderUpdated` broadcast over Reverb (`private-user.{id}`, `public-market.{symbol}`).
- **Order types**: Limit (GTC/IOC/FOK/POST_ONLY), Market (by base qty or quote amount, with max slippage % guard), Stop-limit (trigger monitor job on each trade/price tick).
- **Order book cache**: Redis sorted sets per side; snapshot + incremental depth updates broadcast every 250ms (aggregated by price level).
- **Fees**: maker/taker per market, tier discounts by 30d volume (config table), fees charged in received asset, credited to `system_fee`.
- **Liquidity mode** (`TRADING_LIQUIDITY_MODE`):
  - `internal` — pure user order book.
  - `market_maker` — platform-owned MM account runs `trading:market-maker` bot quoting around external index price with configurable spread/depth, funded from a real system wallet (its inventory is on the ledger like any user).
- **Candles**: built from trades in real time (Redis) and persisted each minute; chart initial load via `/api/markets/{symbol}/candles`.
- **Circuit breaker**: price moves > X% in 1 min → market `halted` for N minutes + admin alert.
- Cancel-all on account freeze.

### 8.8 Staking (Earn)
- **Yield must be funded**: every accrual is a ledger transfer from `system_yield_pool` → user `staking` wallet. If the pool balance for that asset is insufficient, the accrual job stops for that product, sets product `paused`, and fires a critical admin alert. Never mint.
- Product page must show: APR (labelled "variable" when `apr_is_variable`), lock period, `yield_source_description`, early redemption terms, capacity remaining, and a risk disclosure. No "guaranteed" wording anywhere (enforced by a test scanning lang files and Blade for "guaranteed", "risk-free", "no risk").
- **Flexible**: subscribe any time (from spot available → staking), interest accrues daily from T+1, redeem any time with principal available T+1 (configurable), accrued interest paid daily to staking wallet.
- **Fixed**: locked for `lock_days`; daily accrual recorded; at maturity principal + interest released to spot (or auto-renew if opted). Early redemption allowed only if product permits; penalty = forfeit accrued or percent of principal per product config.
- Daily interest = principal × APR / 365 (bcmath, scale 18, truncate to asset precision).
- Jobs: `staking:accrue` daily 00:05 UTC (idempotent per subscription/date, chunked 500), `staking:mature` hourly, `staking:process-redemptions` every 10 min, `staking:capacity-check` hourly.
- Admin: create/edit products (APR changes apply to new subscriptions only unless variable), fund yield pool (maker-checker), pause/close products, see liability forecast (interest owed over next 30/90 days vs. pool balance).

### 8.9 Copy trading
- Lead trader application (tier 3, ≥ 30 days trading history, min equity) → admin approval.
- Leader profile metrics — computed nightly from actual fills/equity snapshots: ROI 7d/30d/90d/all, PnL, max drawdown, win rate, avg holding time, trades count, Sharpe (30d), AUM, current followers, copiers' aggregate PnL. Equity curve chart. Show "Past performance does not guarantee future results."
- Follower settings: allocation (moved spot → copy wallet), mode (fixed amount per trade | proportional to leader's position size relative to leader equity), max per trade, copy stop-loss % on allocation (stop copying + close positions), take-profit %, markets filter.
- Mirroring: listener on leader's `OrderFilled` (spot, source=user) → `MirrorLeaderOrderJob` per follower (queue `copy`, high priority) → places market order sized per settings in follower's copy wallet → records `copy_orders` (skipped with reason if insufficient balance/min notional/paused).
- Profit share: high-water-mark model, settled weekly (Mon 00:30 UTC) and on unfollow; share_rate × (equity − HWM) if positive; ledger transfer follower → leader.
- Leader can't trade markets outside allowed list while having followers; admin can suspend leader (followers auto-paused + notified).

### 8.10 Referrals
- Referral commission = % of **actual trading fees** paid by referee for 12 months (config). Paid daily from `system_fee` wallet. No sign-up cash bonuses that can be withdrawn without activity (prevents farming); any promo credits are locked until trading volume threshold met.

### 8.11 Notifications — event × channel matrix
`M` = mandatory (cannot be disabled), `O` = optional (default on), `—` = not sent.

| Event | In-app | Email | SMS | Push |
|---|---|---|---|---|
| Login from new device/IP | M | M | O | O |
| Password / email / phone changed | M | M | M | O |
| 2FA enabled / disabled | M | M | M | O |
| Account frozen / unfrozen | M | M | M | O |
| OTP codes | — | M (email OTP) | M (phone OTP) | — |
| KYC submitted / approved / rejected | M | M | O | O |
| Deposit detected / confirmed / credited | M | M | O | O |
| Manual deposit under review / rejected | M | M | O | O |
| Withdrawal requested / approved / completed | M | M | M | O |
| Withdrawal rejected / failed | M | M | M | O |
| New whitelist address added | M | M | M | O |
| Order filled / partially filled | O | O | — | O |
| Stop order triggered | O | O | — | O |
| Staking subscribed / matured / redeemed | M | O | — | O |
| Staking product paused | M | M | — | O |
| Copy: started / stopped / stop-loss hit | M | M | O | O |
| Copy: leader suspended | M | M | O | O |
| Profit share settled | M | O | — | O |
| Support reply received | M | M | — | O |
| Price alert (user-defined) | O | O | O | O |
| Security / compliance notice from admin | M | M | O | O |

Implementation: one Laravel Notification class per event, `via()` resolves channels from matrix + preferences; Mailables use a branded dark Markdown theme (logo, anti-phishing code, footer with "never share your OTP", freeze-account link); SMS templates ≤ 160 chars, no links in OTP SMS; all notifications queued on `notifications` queue; failures retried 3× with backoff, then logged. Admin broadcast tool (segment by tier/country/activity) with preview + scheduled send.

### 8.12 Support tickets
- Categories: Deposit, Withdrawal, Trading, Staking, Copy Trading, KYC, Account Security, Other. Ticket can link to a specific deposit/withdrawal/order (pre-filled from those pages: "Report a problem").
- SLA by priority (urgent 1h, high 4h, normal 24h, low 72h first response); breach → escalation notification to support lead.
- Auto-assign round-robin among online agents with matching category skill; internal notes; canned responses with variables; attachments (same upload rules); CSAT rating on resolve; auto-close after 72h `awaiting_user`.
- Realtime replies via Reverb.

### 8.13 Admin panel (Filament, `/admin`, separate `admin` guard, mandatory 2FA, IP allowlist optional)
Roles & permissions (seeded): `super_admin`, `admin`, `compliance`, `finance`, `support_lead`, `support_agent`, `read_only`.
Pages/resources:
- **Overview dashboard**: users (total/new/KYC’d), deposits/withdrawals 24h, trading volume, fee revenue, staking TVL and liability forecast vs. yield pool, pending queues (KYC, manual deposits, withdrawals, tickets, AML alerts), system health (queue lag, engine heartbeat per market, webhook failures, reconciliation status).
- Users (profile, balances, ledger history, devices, login history, KYC, freeze/unfreeze, force logout, reset 2FA via maker-checker).
- KYC review queue (side-by-side document viewer, provider results, approve/reject templates).
- Deposits (filters, manual review with pipeline results, credit/reject).
- Withdrawals (risk breakdown, approve/2nd approve/reject, enter tx hash for manual payout).
- Ledger explorer (journals by reference, entries, balanced check), system wallets, reconciliation reports.
- Assets / networks / asset_networks (deposit/withdraw toggles, fees, confirmations, addresses).
- Markets (fees, tick/lot, halt/resume, price bands), orders, trades.
- Staking products, subscriptions, yield-pool funding (maker-checker), liability forecast.
- Copy trading: applications, leaders, relationships, settlements.
- Support: tickets, categories, canned responses, SLA report.
- Notifications: templates editor (email/SMS per event), broadcast tool, SMS logs and cost report.
- Compliance: AML alerts queue, rules config, suspicious activity report export, audit log viewer (read-only).
- Settings: all `config/platform.php` keys editable with validation; changes audited; fee changes via maker-checker.
- Countries: allow/restrict/block, per-country tier caps, required documents, disabled features, SMS route.
- Languages & translations: translation status per locale, missing-key report.
- Reports: CSV/XLSX export for every table view; daily finance report emailed at 07:00 in `ADMIN_TIMEZONE`.

### 8.14 Compliance hooks (build the plumbing; legal content via config)
- Platform must not go live until the operator confirms licensing/registration in its home jurisdiction ({{HOME_JURISDICTION}} / {{REGULATOR}}) and in every country it actively serves — `PLATFORM_LIVE_MODE` defaults to `false`, which keeps deposits disabled and shows a banner; flipping it requires super_admin + documented checklist in RUNBOOK. Countries can be enabled one by one as licensing allows.
- Geo-restriction: block registration and login from `blocked` countries by declared residence, KYC document country, and IP geolocation (MaxMind GeoLite2, updated weekly); VPN/proxy detection raises a risk flag (not an auto-block). Existing users in a newly blocked country → withdraw-only mode + notification.
- AML rules (configurable thresholds): large single deposit/withdrawal, structuring (many just-below-threshold transactions), rapid in-out, multiple accounts on the same device or same `document_number_hash`, IP country ≠ residence country repeatedly, high-risk country, duplicate tx claims, interaction with flagged wallet addresses (optional blockchain analytics driver: Chainalysis / TRM / Elliptic, `CHAIN_ANALYTICS_DRIVER=null` by default).
- Travel Rule (FATF Recommendation 16): withdrawals and deposits above `TRAVEL_RULE_THRESHOLD_USD` (default 1000, configurable per country) collect originator/beneficiary data; withdrawal form asks "Is this your own wallet or another person/exchange?" and collects beneficiary name + VASP when needed; data exchanged via `TravelRuleProvider`.
- Sanctions/PEP screening via KYC provider at tier upgrade and nightly rescreen.
- Data protection (multi-regime): consent records per document version and jurisdiction (`consents`), GDPR/UK GDPR/NDPA/POPIA-style rights — data export (JSON/CSV), rectification, erasure request handled as account closure with legally required retention kept separate; cookie consent banner (essential/analytics/marketing) where required by residence country; data processing register in `docs/PRIVACY.md`.
- Legal pages (Terms, Risk Disclosure, Privacy, Cookies, AML Policy) per locale and optionally per jurisdiction from `resources/legal/{locale}/{jurisdiction?}/*.md`, versioned; users must re-accept on material version change.
- Tax: monthly and yearly statements (PDF + CSV) with trades, deposits, withdrawals, staking rewards and fees, valued in the user's preferred fiat at transaction time; generic CSV export compatible with common crypto-tax tools.

### 8.15 Internationalisation & additional features
- **Languages**: ship `en` complete; scaffold {{LANGUAGES}} (default: en, fr, es, pt, ar, zh, ru, tr) with `lang/{locale}.json`; missing keys fall back to English and are logged; `php artisan i18n:missing` lists gaps. Locale auto-detected from `Accept-Language`, saved on profile, used for emails/SMS too.
- **Currencies**: any active fiat in `fiat_currencies` can be the display currency; FX refreshed every 15 min; always label converted values as estimates.
- **Price alerts**: user sets above/below/% change per market; checked on every price tick via `PriceAlertMatcher` (Redis sorted sets for O(log n) matching); delivered through the notification matrix.
- **Push notifications**: FCM + Web Push drivers, per-device subscriptions, payloads contain no sensitive amounts on lock screen (configurable).
- **Internal transfers**: send crypto to another user by email/UID, free and instant, ledger journal, 2FA required, recipient must be T1+, counts toward withdrawal limits for AML.
- **Fiat on-ramp (optional, off by default)**: widget integration with a licensed third-party on-ramp (`ONRAMP_DRIVER=null|moonpay|transak|banxa`); the third party handles card/bank payment and KYC for fiat, crypto lands in the user's deposit address and flows through Rail A.
- **Custody (production)**: hot/cold split with a qualified custody/wallet-infrastructure provider (`CUSTODY_DRIVER=manual|fireblocks|bitgo`); app never holds private keys; hot wallet target % configurable; auto sweep alerts when hot balance exceeds threshold.
- **Status page & maintenance**: public `/status` showing per-asset deposit/withdrawal status, market status, and incidents posted from admin; per-asset maintenance toggles show banners on deposit/withdraw screens.
- **PWA**: installable manifest, offline shell, app icons; native apps later via the same API.
- **Announcements & help centre**: admin-managed announcements (per locale, per country targeting) and FAQ articles linked from support ticket creation (deflection).

---

## 9. AUTOMATION — SCHEDULER, QUEUES, DAEMONS

### 9.1 `routes/console.php` schedule (all `->withoutOverlapping()->onOneServer()`)
| Command | Frequency | Purpose |
|---|---|---|
| `prices:sync` | every 10s (via loop daemon) / `everyMinute` fallback | refresh price feed cache, persist ticks |
| `deposits:poll-pending` | every 5 min | recover missed webhooks |
| `deposits:verify-manual` | every minute | drive manual verification retries |
| `withdrawals:sync-status` | every 2 min | poll payout status |
| `withdrawals:auto-process` | every minute | process auto-approved withdrawals |
| `trading:trigger-stops` | every 5s (daemon) | stop-limit triggers |
| `trading:expire-orders` | every minute | IOC/expired cleanup |
| `candles:persist` | every minute | flush candles |
| `staking:accrue` | daily 00:05 UTC | daily interest |
| `staking:mature` | hourly | maturities |
| `staking:process-redemptions` | every 10 min | release redemptions |
| `staking:capacity-check` | hourly | pool sufficiency alerts |
| `copy:compute-stats` | daily 00:20 UTC | leader metrics |
| `copy:check-stop-loss` | every minute | follower stop-loss |
| `copy:settle-profit-share` | weekly Mon 00:30 UTC | settlements |
| `referrals:pay-commissions` | daily 01:00 UTC | referral payouts |
| `ledger:snapshot` | daily 00:01 UTC | balance snapshots |
| `ledger:reconcile` | hourly | liabilities vs custody, auto-halt on mismatch |
| `ledger:invariant-check` | every 15 min | balanced journals, no negatives → page on failure |
| `kyc:rescreen` | daily 02:00 UTC | sanctions/PEP rescreen |
| `aml:scan` | every 15 min | run AML rules |
| `support:sla-check` | every 5 min | SLA breach escalation |
| `support:auto-close` | hourly | close stale tickets |
| `reports:daily-finance` | daily 06:00 UTC | email finance report |
| `backup:run` (spatie/laravel-backup) | daily 03:00 UTC | DB + files backup, 30-day retention |
| `fx:sync` | every 15 min | fiat display rates |
| `alerts:match` | on price tick (daemon) / every minute fallback | price alerts |
| `kyc:expiry-check` | daily 04:00 UTC | document expiry reminders / tier downgrade |
| `geoip:update` | weekly | refresh MaxMind database |
| `statements:generate` | monthly 1st 03:30 UTC | monthly statements |
| `recovery:process-cooling` | every 10 min | complete account recoveries after cooling |
| `horizon:snapshot`, `pulse:check` | every 5 min | monitoring |
| `model:prune` | daily | prune logs per retention |

### 9.2 Queues (Horizon supervisors)
`critical` (ledger, withdrawals, deposits credit), `trading`, `copy`, `webhooks`, `notifications`, `kyc`, `default`, `reports`. Critical and trading get dedicated processes; `tries`, `backoff`, `timeout` set per job; failed jobs alert to Slack/email.

### 9.3 Supervisor daemons (`deploy/supervisor/*.conf`)
`horizon`, `reverb`, `trading-engine-{market}` (one per active market, auto-generated by `trading:supervisor-config`), `prices-daemon`, `stop-trigger-daemon`, `market-maker` (if enabled). Each daemon writes a heartbeat to Redis every 5s; `health:check` alerts if stale > 30s.

---

## 10. API (v1, for mobile app & API traders)
- `routes/api.php`, Sanctum tokens for first-party mobile; HMAC-signed API keys (`X-API-KEY`, `X-TIMESTAMP`, `X-SIGNATURE` = HMAC-SHA256(secret, timestamp+method+path+body), 30s window) for trading bots.
- Public: markets, ticker, order book depth, trades, candles.
- Private: balances, deposits, withdrawals (read only via API keys — withdrawal creation never via API key), orders (place/cancel/list), trades, staking, copy stats.
- JSON:API-ish responses, cursor pagination, consistent error envelope `{error:{code,message,details}}`.
- OpenAPI 3 spec generated (`dedoc/scramble`) at `/docs/api`.

---

## 11. SECURITY BASELINE
- Everything in the `security-review` skill, plus:
- Encrypted casts for 2FA secrets, KYC provider results, API secrets (stored hashed; shown once).
- `SecurityHeaders` middleware with strict CSP (nonce-based for Livewire/Alpine).
- Login throttling: 5/min per email+IP, progressive lockout; CAPTCHA (Cloudflare Turnstile) after 3 failures and on register.
- Admin: separate subdomain recommended, IP allowlist, session timeout 15 min, every action audited.
- Withdrawal address poisoning defence: show first 6 + last 6 chars highlighted, warn if address resembles a recent deposit source.
- Secrets management doc in RUNBOOK; no secrets in repo; `.env.example` complete with comments.

---

## 12. PERFORMANCE TARGETS
- Order placement p95 < 150ms to acceptance; matching throughput ≥ 500 orders/sec per market on a 4 vCPU box (benchmark command `trading:benchmark` included).
- Dashboard TTFB < 300ms; Lighthouse mobile ≥ 85 performance, 100 accessibility target ≥ 95.
- Cache: settings, assets, markets, tiers (tag-based invalidation on admin edit).

---

## 13. TESTING STRATEGY
- Pest unit + feature + architecture tests (`arch()->expect('App\Domain')->not->toUse('Illuminate\Support\Facades\DB')` except Repositories/LedgerService; no `float` type in Domain).
- `LedgerInvariantTest`, `MoneyLanguageTest` (forbidden "guaranteed/risk-free" wording), webhook fixture tests per provider, matching engine property tests (random order streams → conservation of assets, no crossed book after matching).
- Browser smoke tests with Laravel Dusk for: register → KYC T1 (fake provider) → deposit (fake gateway) → trade → stake → withdraw.
- Factories + seeders for every model.

---

## 14. DEVOPS
- `Makefile`: `setup`, `dev`, `test`, `lint`, `analyse`, `fresh` (migrate:fresh --seed demo), `engine`, `horizon`.
- Docker (Sail) services: app, mysql, redis, mailpit, minio, reverb, clamav.
- GitHub Actions `.github/workflows/ci.yml`: PHP 8.3 + MySQL 8 + Redis services → composer install → npm ci && npm run build → pint --test → larastan → pest --parallel --coverage --min=85 → composer audit → npm audit --audit-level=high.
- Deployment doc for Ubuntu 24.04 + Nginx + PHP-FPM + Supervisor (and optional Forge/Envoyer notes). Zero-downtime: `php artisan down --render` not used for routine deploys; engines drained gracefully (`trading:engine` handles SIGTERM after finishing current order).

---

## 15. CONFIGURATION — `.env.example` keys (all must exist with comments)
```
APP_NAME="{{PLATFORM_NAME}}"  APP_DEMO=false  PLATFORM_LIVE_MODE=false  APP_TIMEZONE=UTC  ADMIN_TIMEZONE=UTC
APP_LOCALE=en  APP_FALLBACK_LOCALE=en  SUPPORTED_LOCALES=en,fr,es,pt,ar,zh,ru,tr  DEFAULT_DISPLAY_FIAT=USD
DB_CONNECTION=mysql ...  REDIS_CLIENT=phpredis ...  QUEUE_CONNECTION=redis  BROADCAST_CONNECTION=reverb
REVERB_APP_ID= REVERB_APP_KEY= REVERB_APP_SECRET= REVERB_HOST= REVERB_PORT=
FILESYSTEM_DISK=s3  AWS_* (or MinIO)
MAIL_MAILER=ses|resend  MAIL_FROM_ADDRESS=  MAIL_FROM_NAME=
PAYMENT_GATEWAY=nowpayments|coingate|fake  NOWPAYMENTS_API_KEY= NOWPAYMENTS_IPN_SECRET= NOWPAYMENTS_SANDBOX=true  COINGATE_API_KEY= COINGATE_SANDBOX=true
PAYOUT_DRIVER=manual|gateway
ETHERSCAN_API_KEY= BSCSCAN_API_KEY= TRONGRID_API_KEY= BLOCKSTREAM_BASE_URL=
SMS_DRIVER=failover  SMS_PRIMARY=twilio SMS_FALLBACK=vonage  TWILIO_SID= TWILIO_TOKEN= TWILIO_VERIFY_SID=  VONAGE_KEY= VONAGE_SECRET=
KYC_DRIVER=sumsub|onfido|veriff|fake  SUMSUB_APP_TOKEN= SUMSUB_SECRET= SUMSUB_WEBHOOK_SECRET=  ONFIDO_API_TOKEN= ONFIDO_WEBHOOK_TOKEN=  VERIFF_API_KEY= VERIFF_SHARED_SECRET=  KYC_RETENTION_YEARS=5
PUSH_FCM_CREDENTIALS=  VAPID_PUBLIC_KEY= VAPID_PRIVATE_KEY=
FX_DRIVER=openexchangerates  OPENEXCHANGERATES_APP_ID=
MAXMIND_LICENSE_KEY=
TRAVEL_RULE_DRIVER=null|notabene|sumsub  TRAVEL_RULE_THRESHOLD_USD=1000
CHAIN_ANALYTICS_DRIVER=null|chainalysis|trm|elliptic  CHAIN_ANALYTICS_API_KEY=
CUSTODY_DRIVER=manual|fireblocks|bitgo  ONRAMP_DRIVER=null|moonpay|transak|banxa
PRICE_FEED=binance  PRICE_FEED_FALLBACK=coingecko
TRADING_LIQUIDITY_MODE=internal|market_maker
TURNSTILE_SITE_KEY= TURNSTILE_SECRET=
SENTRY_LARAVEL_DSN=
ADMIN_IP_ALLOWLIST=
```

---

## 16. SEEDERS
- Countries (full ISO 3166-1 list; statuses from {{RESTRICTED_COUNTRIES}}, with comprehensively sanctioned jurisdictions blocked by default), fiat currencies, locales.
- Roles/permissions, KYC tiers, assets (BTC, ETH, USDT, BNB, SOL, TRX, USDC), networks (BTC, ERC20, BEP20, TRC20, SOL), asset_networks with sane fees/confirmations, markets, system wallets, support categories, canned responses, notification templates, default settings.
- `DemoSeeder` (only when `APP_DEMO=true`): demo users per tier, funded via proper ledger journals from a `system_demo_faucet` wallet, sample orders/trades generated by actually running the matching engine, staking products funded via yield pool journal, two demo lead traders whose stats come from their seeded trades. Banner "DEMO ENVIRONMENT" on every page.

---

## 17. BUILD PHASES & ACCEPTANCE CRITERIA

| Phase | Scope | Done when |
|---|---|---|
| 0 | Repo, Sail, Makefile, CI, CLAUDE.md, skills, hooks, docs skeleton, Decimal class, tokens + Tailwind + base layout + UI component library | CI green; component gallery page `/dev/ui` renders all components in dark/light at 360/1280 |
| 1 | Identity: auth, 2FA, devices, sessions, anti-phishing, security settings, rate limits, audit log | Pest auth suite green; security-review pass |
| 2 | Assets, Ledger, wallets, system accounts, invariants, snapshots, reconciliation scaffolding | LedgerInvariantTest + concurrency tests green |
| 3 | KYC tiers + provider drivers (fake + real) + admin review | User can reach T2 with fake provider; limits enforced |
| 4 | Deposits rail A (gateway) + rail B (manual pipeline) + admin review | Fixture webhooks credit correctly; duplicate tx/receipt rejected; on-chain verifier fakes pass all cases |
| 5 | Withdrawals: whitelist, OTP, risk engine, maker-checker, payout drivers | Concurrency + reversal tests green; 24h locks enforced |
| 6 | Notifications matrix, Mailables theme, SMS failover + country routing, push drivers, preferences, broadcast | Every matrix row has a test asserting channels |
| 7 | Trading: markets, orders, engine daemon, settlement, order book, candles, Reverb UI, stop orders, circuit breaker | Property tests pass; benchmark ≥ target; trade UI live-updates |
| 8 | Staking: products, subscribe/redeem, accrual from yield pool, maturity, admin forecast | Pool-insufficient path pauses product; accrual idempotent |
| 9 | Copy trading: applications, mirroring, stats, stop-loss, profit share | Leader fill mirrors to N followers correctly sized; HWM settlement tested |
| 10 | Referrals, Support tickets, Admin dashboards/reports, Compliance/AML, geo-restriction, Travel Rule, consents | SLA + AML rules tested; blocked-country registration denied; Travel Rule data collected above threshold |
| 11 | i18n (locales, RTL check), multi-fiat display, price alerts, push, internal transfers, statements, account recovery, status page, PWA | `i18n:missing` clean for `en`; RTL layout renders in `ar`; alert fires within 2s of matching tick |
| 12 | Public API + OpenAPI, Dusk E2E, performance pass, security review, deployment docs | Full E2E green (incl. a non-English, non-USD user journey); Lighthouse targets met; RUNBOOK complete |

At the end, produce `docs/HANDOFF.md`: what was built, how to run, all env keys needed for production, known limitations, and the go-live checklist (regulatory registration confirmed, custody set up, real provider keys, backups verified, pen-test scheduled, PLATFORM_LIVE_MODE flip procedure).

---

## 18. OPERATOR INPUTS TO FILL BEFORE RUNNING
- `{{PLATFORM_NAME}}`, logo SVG path, domain
- `{{T1_LIMIT_USD}}`, `{{T2_LIMIT_USD}}`, `{{T3_LIMIT_USD}}`
- `{{HOME_JURISDICTION}}`, `{{REGULATOR}}`, and the countries you are licensed to serve
- `{{RESTRICTED_COUNTRIES}}` (blocked / restricted lists)
- `{{LANGUAGES}}` to scaffold beyond English
- Initial asset/market list (defaults in Section 16 if left unchanged)
- Fee schedule (defaults: maker 0.10%, taker 0.10%; withdrawal fees per network in seeder)

**Begin with Phase 0. Do not ask for confirmation. Report progress only at the end of each phase in `docs/PROGRESS.md`.**
