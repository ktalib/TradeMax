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

The application's DB user must not be able to change history. After migrations, as a privileged user:

```sql
-- the app user keeps SELECT/INSERT on audit_logs, loses UPDATE/DELETE
REVOKE UPDATE, DELETE ON trademax.audit_logs FROM 'trademax_app'@'%';
```

(MySQL grants are additive: grant the app user privileges per table rather than `ALL ON trademax.*`, or the revoke has no effect.) The `AuditLog` model also refuses updates and deletes.

## Staff accounts

Create staff with `php artisan admin:create someone@company.com --name="Full Name" --role=finance`. The password is asked for interactively. Roles: super_admin, admin, compliance, finance, support_lead, support_agent, read_only (`RolesAndPermissionsSeeder`).

## Account freezes

Users freeze their own account from the security page or the link in any security email. That revokes API keys, ends other sessions and stops withdrawals. Unfreezing is a staff action after an identity check (admin panel, Phase 10).
