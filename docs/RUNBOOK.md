# Runbook — TradeMax

> Operational procedures for production. Expand as phases complete.

## Services

Production hosting is not decided yet (ADR-0004). Whatever is chosen must keep these long-running processes alive with auto-restart: one `queue:work` per queue (critical, trading, copy, webhooks, notifications, kyc, reports, default), Reverb, one `trading:engine` per active market, and the market maker if enabled; plus `schedule:run` every minute. Stop an engine gracefully with `php artisan trading:stop {market}`.

Every long-running process writes a heartbeat to Redis every 5s; `health:check` alerts if stale > 30s.

## Daemons & scheduler

See master build prompt Section 9 for the full schedule table and queue names. All scheduled tasks run `->withoutOverlapping()->onOneServer()`.

## Incident playbooks (placeholders — fill as built)

- **Ledger mismatch** — `ledger:reconcile` (hourly) compares the newest custody figure per asset with -(system_hot + system_cold) at that figure's time. Beyond the asset's `reconcile_tolerance` it halts that asset's withdrawals, logs a critical alert and writes a `mismatch` report. Steps: re-count custody and record it again (a stale or mistyped figure is the usual cause), then inspect the journals since the last `matched` report. Correct a wrong journal with a reversal (`ReverseJournal`), never a direct edit. Resume withdrawals as staff (`ResumeAssetWithdrawals`, audited) once a run reports `matched`.
- **Ledger invariant violation** — `ledger:invariant-check` (every 15 min) exits non-zero and logs critical. `balance_drift` means something wrote a balance outside `LedgerService`; treat it as an incident: halt withdrawals of the asset, find the write (audit log, DB logs), and correct with journals. Never "fix" the wallet row by hand.
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

## Ledger

- **Balances change only through `LedgerService::post()`.** No SQL edits to `wallets`, `journals` or `journal_entries`, ever. Corrections are reversal journals.
- **Custody figures:** finance records what custody holds per asset (`RecordCustodyBalance`, admin panel). Record one for every asset before go-live; until then `ledger:reconcile` reports the asset as `missing`.
- **Snapshots:** `ledger:snapshot` closes the previous UTC day at 00:01. To backfill a missed day: `php artisan ledger:snapshot --date=2026-10-10` (safe to re-run).
- **Proof of liabilities:** `php artisan ledger:proof-of-liabilities BTC --date=2026-10-10` builds the tree from that day's snapshots. Publishing the root is a separate staff action (Phase 10).
- **System wallets:** every asset has `system_fee`, `system_yield_pool`, `system_hot`, `system_cold`, `system_suspense` (plus `system_demo_faucet` in demo). `db:seed --class=SystemWalletSeeder` creates any that are missing. `system_hot`/`system_cold` are negative by design: their absolute value is what custody should hold.
- **Database grants:** like `audit_logs`, the app user should lose DELETE on `journals`, `journal_entries`, `wallets`, `wallet_balance_snapshots`, `custody_balances`, and UPDATE on all of them except `wallets`.
