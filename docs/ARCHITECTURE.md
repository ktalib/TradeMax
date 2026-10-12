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

## Module sections

_(One section per module added as it is built — see `new-domain-module` skill step 10.)_
