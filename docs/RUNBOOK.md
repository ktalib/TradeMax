# Runbook — TradeMax

> Operational procedures for production. Expand as phases complete.

## Services

Production hosting is not decided yet (ADR-0004). Whatever is chosen must keep these long-running processes alive with auto-restart: one `queue:work` per queue (critical, trading, copy, webhooks, notifications, kyc, reports, default), Reverb, one `trading:engine` per active market, and the market maker if enabled; plus `schedule:run` every minute. Stop an engine gracefully with `php artisan trading:stop {market}`.

Every long-running process writes a heartbeat to Redis every 5s; `health:check` alerts if stale > 30s.

## Daemons & scheduler

See master build prompt Section 9 for the full schedule table and queue names. All scheduled tasks run `->withoutOverlapping()->onOneServer()`.

## Incident playbooks (placeholders — fill as built)

- **Ledger mismatch** — `ledger:reconcile` auto-halts withdrawals for the asset on mismatch > tolerance. Steps: freeze, inspect journals, post reversal, resume.
- **Staking yield pool insufficient** — accrual stops, product `paused`, critical admin alert. Steps: fund pool (maker-checker), resume product, re-run `staking:accrue`.
- **Trading engine stale heartbeat** — drain/restart engine; orders remain in Redis stream; verify no crossed book.
- **Webhook failure spike** — check `payment_webhooks.error`, confirm provider, use `deposits:poll-pending` to recover.

## Go-live gate

`PLATFORM_LIVE_MODE=false` by default (deposits disabled, banner shown). Flipping it requires super_admin + the documented checklist in `docs/HANDOFF.md`.

## Audit log immutability

The DB user for the app has **no UPDATE/DELETE grant** on `audit_logs`. Document and verify this in the deployment.
