# Progress — TradeMax

> Report progress at the end of each phase. Status: `Not started | In progress | Blocked | Done`.

## Build phases

| Phase | Scope | Done when | Status |
|---|---|---|---|
| 0 | Repo, Windows local env (Laragon), composer scripts, CI, CLAUDE.md, skills, hooks, docs skeleton, Decimal class, tokens + Tailwind + base layout + UI component library | CI green; component gallery `/dev/ui` renders all components in dark/light at 360/1280 | Done (2026-10-12) — visual check at 360/1280 pending owner review |
| 1 | Identity: auth, 2FA, devices, sessions, anti-phishing, security settings, rate limits, audit log | Pest auth suite green; security-review pass | Not started |
| 2 | Assets, Ledger, wallets, system accounts, invariants, snapshots, reconciliation scaffolding | LedgerInvariantTest + concurrency tests green | Not started |
| 3 | KYC tiers + provider drivers (fake + real) + admin review | User reaches T2 with fake provider; limits enforced | Not started |
| 4 | Deposits rail A (gateway) + rail B (manual pipeline) + admin review | Fixture webhooks credit correctly; duplicate tx/receipt rejected; on-chain verifier fakes pass all cases | Not started |
| 5 | Withdrawals: whitelist, OTP, risk engine, maker-checker, payout drivers | Concurrency + reversal tests green; 24h locks enforced | Not started |
| 6 | Notifications matrix, Mailables theme, SMS failover + country routing, push drivers, preferences, broadcast | Every matrix row has a test asserting channels | Not started |
| 7 | Trading: markets, orders, engine daemon, settlement, order book, candles, Reverb UI, stop orders, circuit breaker | Property tests pass; benchmark ≥ target; trade UI live-updates | Not started |
| 8 | Staking: products, subscribe/redeem, accrual from yield pool, maturity, admin forecast | Pool-insufficient path pauses product; accrual idempotent | Not started |
| 9 | Copy trading: applications, mirroring, stats, stop-loss, profit share | Leader fill mirrors to N followers correctly sized; HWM settlement tested | Not started |
| 9b | Signals: analyst + indicator signals, outcome engine, track records, follow/alerts, admin, generated demo data (ADR-0006) | Outcome/immutability/stats tests green; demo seeder only under APP_DEMO; flag + country gating | Not started |
| 10 | Referrals, Support tickets, Admin dashboards/reports, Compliance/AML, geo-restriction, Travel Rule, consents | SLA + AML rules tested; blocked-country registration denied; Travel Rule data collected above threshold | Not started |
| 11 | i18n (locales, RTL), multi-fiat display, price alerts, push, internal transfers, statements, account recovery, status page, PWA | `i18n:missing` clean for `en`; RTL renders in `ar`; alert fires within 2s of matching tick | Not started |
| 12 | Public API + OpenAPI, Dusk E2E, performance pass, security review, deployment docs | Full E2E green (incl. non-English, non-USD journey); Lighthouse targets met; RUNBOOK complete | Not started |

## Per-phase rule

Do not start a phase until the previous phase's acceptance checks pass. After every phase run:

```
composer test && composer analyse && composer lint
npm run build
```

Commit green phases with Conventional Commits.

## Log

- 2026-10-12 — Phase 0 (partial): created `CLAUDE.md`/`AGENTS.md`, 7 skills, `.claude/settings.json` hooks, docs skeleton, and resolved operator inputs (ADR-0001/0002).
- 2026-10-12 — Phase 0 (planning): added `docs/PLAN.md` — full program plan for Phases 0–12 (per-phase goals, deliverables, schema, jobs, acceptance gates, critical path, driver/queue maps, risk register).
- 2026-10-12 — Spec review: corrected 10 defects/contradictions in the master prompt (ADR-0003); PLAN, AGENTS.md, skills and hooks updated. `CLAUDE.md` now imports `AGENTS.md`.
- 2026-10-12 — Platform switched to Windows dev + Windows Server/cPanel production (ADR-0004): removed Ubuntu/Supervisor/Sail/Horizon/pcntl assumptions.
- 2026-10-12 — Production hosting deferred (ADR-0004 amended); Windows remains the dev platform.
- 2026-10-12 — **Phase 0 done.** Laravel 12 skeleton; `Decimal` (bcmath, scale 18) and locale-aware `DecimalFormatter` with full test suites; `HasPublicUlid`; 14 domain module trees + `DomainServiceProvider`; `config/platform|payments|kyc|sms|trading|navigation.php`; design tokens, Tailwind 4 theme, self-hosted fonts, sidebar layout, 22 UI components and the `/dev/ui` gallery; architecture tests (strict types, no floats in money/domain code); CI for Windows + Ubuntu; full `.env.example`. Checks: 67 tests passing, 96.2% line coverage (money code 100%), Larastan level 8 clean, Pint clean, `npm run build` OK. Choices recorded in ADR-0005. Not yet verified: the 360px/1280px visual check (headless browser screenshots weren't available here).
- 2026-10-12 — Local services connected: MySQL `trademax` (InnoDB enforced) + `trademax_test`, Memurai via predis; base migrations run. Signals added to the plan as Phase 9b (ADR-0006).
