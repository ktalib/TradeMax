# Decisions — TradeMax (ADR-style)

> Any non-obvious choice is recorded here. Format: short title, status, context, decision, consequences.

## ADR-0001 — Operator inputs resolved
- **Status:** Accepted
- **Context:** The master build prompt uses `{{PLACEHOLDER}}` values that must be fixed before implementation.
- **Decision:**
  - Platform name: **TradeMax**
  - KYC tier daily-withdraw limits (USD): T1 = 1,000 · T2 = 10,000 · T3 = 50,000
  - Home jurisdiction / regulator: **TBD — registration unconfirmed** (must be documented before `PLATFORM_LIVE_MODE` may be enabled)
  - Restricted countries: comprehensively sanctioned jurisdictions blocked by default; active-serve list TBD
  - Languages scaffolded: `en, fr, es, pt, ar, zh, ru, tr` (English complete)
  - Fee schedule defaults: maker 0.10%, taker 0.10%; per-network withdrawal fees in seeder
  - Initial assets/markets: platform defaults from the master prompt Section 16
- **Consequences:** `PLATFORM_LIVE_MODE=false` until the operator confirms licensing. Tiers/limits are seeded in `config/platform.php` and the `settings` table.

## ADR-0002 — Agent config layout
- **Status:** Accepted
- **Context:** The master prompt targets Claude Code (`.claude/`) but the project may also be driven by other agents.
- **Decision:** Author canonical rules/skills in the Claude Code layout (`.claude/skills/*/SKILL.md`, `.claude/settings.json`). `AGENTS.md` is the single canonical rules file; `CLAUDE.md` only imports it (`@AGENTS.md`). *(Amended 2026-10-12: previously two byte-identical copies synced by hand.)*
- **Consequences:** Other agents read `AGENTS.md`; Claude Code reads it through the import. No manual sync.

## ADR-0003 — Corrections to the master prompt spec
- **Status:** Accepted (2026-10-12)
- **Context:** Review of the master prompt found contradictions and defects that would be built verbatim.
- **Decision:**
  1. **Rail B claim theft.** A shared deposit address + optional sender lets anyone claim another user's tx hash first. Rail B requires a per-user address, a per-user memo/tag, or a mandatory sender address proven by signed message; otherwise `manual_review`, never auto-credit.
  2. **Ledger sign convention.** "Debit `system_hot`" on deposit contradicted "no wallet < 0". Credit increases / debit decreases; user wallets never < 0; `system_hot`, `system_cold`, `system_demo_faucet` are counter-accounts allowed to be negative (`allowsNegative()` on the wallet-type Enum).
  3. **Snapshots.** `LedgerService::post()` no longer writes `wallet_balance_snapshots` (conflicted with UNIQUE(wallet_id, snapshot_date)); snapshots are daily only.
  4. **Module boundaries.** "Events only" was impossible for synchronous ledger calls. Modules may call each other's public Contracts/Actions; Events for side effects; never another module's Models/Repositories/tables.
  5. **DB facade.** `DB::transaction` allowed in Actions; raw queries only in Repositories and `LedgerService`.
  6. **Hot rows.** `system_fee` / `system_hot` are updated atomically without `lockForUpdate` so every market doesn't serialise on one row.
  7. **Trading engine.** MySQL is authoritative, Redis is a cache rebuilt on start; AOF on. Throughput target revised from 500 to ≥ 100 orders/sec/market.
  8. **Copy trading.** Mirrors are limit-IOC with a slippage cap; leader↔follower crosses blocked and alerted; low-volume markets excluded.
  9. **Hooks.** The `Stop` hook ran the full test suite on every turn and failed before `composer.json` existed; it now no-ops until `vendor/` exists and runs without `--parallel`.
  10. **Process.** Human review gates after Phase 2 and Phase 7; jurisdiction must be decided before Phase 3.
- **Consequences:** `docs/PLAN.md`, `AGENTS.md`, the `ledger-posting` and `write-money-tests` skills, and `.claude/settings.json` updated. The master prompt file itself is left unchanged as the historical brief; PLAN + ADRs take precedence.

## ADR-0004 — Windows development; production hosting deferred
- **Status:** Accepted for development; hosting **deferred** (2026-10-12)
- **Context:** The team builds on Windows. The master prompt assumed Linux everywhere (Sail/Docker, Makefile, Supervisor, Horizon, `pcntl`, Ubuntu + Nginx + PHP-FPM). Production hosting (Windows Server, cPanel or other) will be decided later.
- **Decision:**
  - Local dev: Laragon (or Herd for Windows), MySQL 8, Memurai/Redis, Mailpit; `composer` scripts replace the Makefile.
  - No Horizon (needs `pcntl`/`posix`): plain `queue:work` per queue, monitored with Pulse + Filament failed-jobs page.
  - No `pcntl` anywhere: concurrency tests use `proc_open`; the engine stops via a Redis flag (`trading:stop`) instead of SIGTERM.
  - Upload virus scan driver: `defender|clamav|null`.
  - Ubuntu/Supervisor deployment docs removed. Hosting must be chosen before Phase 7; the host must run long-lived processes with auto-restart. Shared cPanel can't, so it would limit the platform to non-trading modules.
- **Consequences:** Master prompt §1 (Sail, Horizon), §9.3 (Supervisor) and §14 (Makefile, Sail, Ubuntu) are superseded. Deployment docs are written once hosting is chosen.

## ADR-0005 — Phase 0 build choices
- **Status:** Accepted (2026-10-12)
- **Context:** Phase 0 was built on the team's Windows machine (WAMP: PHP 8.2.0, MySQL 8.0.31 not running, no Redis).
- **Decision:**
  - **PHP 8.2** instead of the plan's 8.3. Laravel 12 supports 8.2; CI pins 8.2 so the two match. Move both to 8.3 together when WAMP is upgraded.
  - **Packages arrive with the phase that first uses them** instead of all in Phase 0 (PLAN §3.1). Phase 0 installs Livewire, Pest, Larastan, Rector and Pint. Fortify, Sanctum, spatie/permission and activitylog arrive in Phase 1; Filament with the first admin screens; Reverb, Pulse, Sentry, backup, QR and image handling in their owning phases. Unused, unconfigured packages would only add attack surface and upgrade work.
  - **Tests use in-memory SQLite** until Phase 2. Ledger concurrency tests need MySQL row locks, so Phase 2 switches the test database to MySQL.
  - **Design system follows the approved UI preview**: collapsible sidebar, refined dark-first tokens, and Sora added for headings and large figures (Inter for UI text, JetBrains Mono for numbers and addresses), all self-hosted via @fontsource.
  - **Livewire is bundled through Vite** (`livewire.esm` + `@livewireScriptConfig`) so the component library's Alpine behaviour (`resources/js/ui.js`) registers before Alpine starts.
  - **`Model::shouldBeStrict()`** outside production, a superset of the plan's `preventLazyLoading()`.
  - **Cache and queue default to `database`** in `.env.example` so `composer setup` works without Redis; Redis is required before Phase 7.
  - **Local services (2026-10-12):** MySQL 8.0.31 from WAMP with databases `trademax` and `trademax_test`; Memurai on 6379. Laravel uses the **predis** client (WAMP has no `phpredis` DLL); switching later is one env change. WAMP's MySQL defaults to MyISAM, so `config/database.php` pins `engine => InnoDB` (the ledger needs transactions and row locks) and the plan's `utf8mb4_0900_ai_ci` collation.
- **Consequences:** PLAN §3.1's package list is satisfied incrementally. Each phase's notes list what it installs.

## ADR-0006 — In-house Signals (Phase 9b)
- **Status:** Accepted (2026-10-12)
- **Context:** The owner wants a Signals feature like the approved UI preview. There are no outside signal providers yet.
- **Decision:**
  - Two in-house sources: **staff analysts** (publish from admin, author ≠ reviewer) and an **automatic indicator engine** (EMA cross, RSI reversal, MACD cross on platform candles, ATR-based levels).
  - Built as **Phase 9b**, after Trading, Staking and Copy Trading, because outcomes are tracked against live prices and candles.
  - **Signals are immutable once published**, and track records count every closed signal including losses; cancelled and expired-without-entry are shown separately, never dropped.
  - **No auto-trading.** "Trade this" only pre-fills the order form.
  - **Off by default** (`signals.enabled=false`) until legal sign-off, with per-country gating, because publishing buy/sell signals can count as investment advice.
  - **Demo data is generated, not typed.** `DemoSignalSeeder` (APP_DEMO only) simulates ~90 days of candles and runs the real indicator and outcome engines over them, so demo track records come from the same code as production.
- **Consequences:** a new `app/Domain/Signals` module, six tables, four scheduled commands and a `signals` queue. PLAN gains a Phase 9b section and a risk-register entry.

<!-- Add new ADRs below. -->
