# Setup — TradeMax

> Target: fresh Windows machine → `git clone` → `copy .env.example .env` → `composer setup` → `composer dev`.

## Prerequisites (Windows)

- WAMP (current dev setup) or Laragon/Herd: PHP 8.2+ with `bcmath`, `intl`, `gd`, `pdo_mysql`; MySQL 8; Composer 2
- Node 20+
- Memurai (Redis for Windows) on port 6379. Laravel talks to it through the `predis` package, so no PHP extension is needed.
- Mailpit (Windows binary) for local mail
- Upload virus scanning uses Windows Defender locally (`VIRUS_SCAN_DRIVER=defender`)

No Docker, Sail, Horizon or `pcntl` (ADR-0004).

### Databases

Create two databases (utf8mb4_0900_ai_ci): `trademax` for the app and `trademax_test` for MySQL-backed tests (from Phase 2). The app forces InnoDB tables, because WAMP's MySQL defaults to MyISAM.

```sql
CREATE DATABASE trademax CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE trademax_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
```

## Quick start (PowerShell)

```powershell
copy .env.example .env
composer setup   # composer install, npm ci, key:generate, migrate --seed
composer dev     # serve + queue workers + reverb + vite + schedule:work
```

## External providers

Every integration is driver-based and swappable via env; each has a `Fake` driver used in tests and demo mode. See the master build prompt Section 6 for the driver contracts and Section 15 for the `.env.example` key list, plus `VIRUS_SCAN_DRIVER=defender|clamav|null` and `BROADCAST_CONNECTION=reverb|pusher`.

## Agent rules

Edit rules in `AGENTS.md` only. `CLAUDE.md` imports it with `@AGENTS.md`.

## Operator inputs (resolved)

| Input | Value |
|---|---|
| Platform name | TradeMax |
| T1 / T2 / T3 daily withdraw (USD) | 1,000 / 10,000 / 50,000 |
| Home jurisdiction / regulator | TBD (unconfirmed) |
| Languages | en, fr, es, pt, ar, zh, ru, tr |
| Maker / taker fee | 0.10% / 0.10% |
| Hosting | TBD (ADR-0004) |

## Production env keys

_(Fill in as phases complete — see `docs/HANDOFF.md` for the go-live checklist.)_
