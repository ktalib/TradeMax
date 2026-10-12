# Changelog

All notable changes to TradeMax. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); this project adheres to Conventional Commits and Semantic Versioning.

## [Unreleased]

### Added
- Phase 2: assets, networks and asset-network routes (7 assets, 5 networks seeded); double-entry ledger with wallets, journals, entries and daily snapshots; `LedgerService::post()` with ordered row locks, hot-row atomic updates, idempotency fingerprints and the sign convention; journal reversals; system wallets per asset; invariant checks, reconciliation with automatic withdrawal halt, and proof of liabilities (Merkle sum tree); tests on MySQL with a parallel-process concurrency suite.
- Phase 1: registration with country gate, E.164 phones, strength and breach-checked passwords, consents; email → device → SMS → authenticator onboarding; device trust and new-device codes; login history and CAPTCHA after failures; Security centre (2FA reset and recovery codes, password, email and phone changes with step-up, anti-phishing code, API keys, sessions, devices); freeze from the security page or the email link; 24h withdrawal lock on security changes; append-only audit log; security headers with CSP nonce; staff roles and `admin:create`.
- Signals planned as Phase 9b (ADR-0006); sidebar entry shown as "Soon".
- MySQL (InnoDB enforced) and Memurai/predis wired for local development.
- Phase 0 (partial): project rules (`CLAUDE.md`, `AGENTS.md`), agent skills under `.claude/skills/`, `PostToolUse`/`Stop` hooks (`.claude/settings.json`), and documentation skeleton under `docs/`.
- Phase 0 (planning): `docs/PLAN.md` — full program plan for Phases 0–12.
- Phase 0: Laravel 12 app; `Decimal`/`DecimalFormatter` money primitives; `HasPublicUlid`; domain module skeleton; platform config files; design tokens, sidebar layout and 22 UI components with a `/dev/ui` gallery; architecture tests; CI (Windows + Ubuntu); documented `.env.example`.
