# Project rules — TradeMax

> Canonical agent rules. `CLAUDE.md` imports this file — edit only here.

## Non-negotiables (money)
- NEVER use float/double for any amount, price, rate, or fee. Use `App\Support\Money\Decimal` (bcmath, scale 18).
- DB columns for amounts: DECIMAL(36,18). Prices: DECIMAL(36,18). Percentages/rates: DECIMAL(10,6).
- Balances are NEVER updated directly. All balance changes go through `LedgerService::post()` which writes a balanced double-entry journal (sum of debits == sum of credits per asset) inside one DB transaction.
- Every balance-mutating operation: DB::transaction + `lockForUpdate()` on affected **user** wallet rows, locked in ascending wallet id order (deadlock prevention). High-traffic system wallets (`system_fee`, `system_hot`) are updated atomically without a row lock (see ADR-0003).
- Sign convention: credit increases a wallet, debit decreases it. User wallets never go below 0; system counter-accounts (`system_hot`, `system_cold`, `system_demo_faucet`) may be negative. Each wallet type Enum declares `allowsNegative()`.
- Every externally-triggered money operation (webhook, job, API call) requires an idempotency key with a UNIQUE index.
- No hard deletes on financial tables. Use status transitions + reversal journal entries.

## Architecture
- Layout: `app/Domain/<Module>/{Models,Actions,Services,Repositories,DTOs,Events,Listeners,Jobs,Enums,Exceptions,Policies}`.
- Controllers/Livewire components are thin: validate (FormRequest / Livewire rules) → call one Action → return.
- Repositories expose interfaces in `Contracts/`, bound in `DomainServiceProvider`.
- State machines: every status column is a backed Enum with an explicit `canTransitionTo()` map; illegal transitions throw.
- Cross-module: a module may call another module's public `Contracts`/`Actions` and use its `DTOs`/`Events`/`Enums` synchronously (e.g. everyone calls `LedgerService::post()`); side effects (notifications, stats, alerts) go via Events. No module touches another module's Models, Repositories or tables.
- `DB::transaction` is allowed in Actions; raw queries only in Repositories and `LedgerService`.
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
- `LedgerInvariantTest` runs on every CI build: for each asset, SUM(debits) == SUM(credits); no user wallet `available`/`locked` < 0; only wallet types with `allowsNegative()` may be negative.
- Concurrency tests spawn parallel PHP processes with `proc_open` (works on Windows). Never depend on `pcntl`/`posix`.

## Platform
- Dev: Windows. Production hosting: not decided yet (ADR-0004). Don't add Supervisor, Horizon, Sail or other Linux-only tooling.
- Commands in docs and scripts must work on Windows: use `php artisan` and `composer` scripts, not bash-only scripts.

## Workflow
- Small commits, Conventional Commits.
- Update docs/DECISIONS.md for any non-obvious choice. `docs/PLAN.md` + ADRs override the master prompt where they conflict.
- Before finishing a task: composer test && composer analyse && composer lint.
