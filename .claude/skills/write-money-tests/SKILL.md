---
name: write-money-tests
description: Use when writing tests for any flow that moves funds.
---
Required cases for every money flow:
- happy path with exact Decimal assertions (string compare, never floats)
- insufficient balance → domain exception, no journal written
- idempotency: same key twice → one journal
- concurrency: run 10 parallel attempts via `proc_open` parallel PHP processes (`tests/Support/ParallelRunner`; no pcntl, we run on Windows) → user balance never negative
- ledger balanced after test (LedgerAssertions::assertBalanced())
- audit_log row exists with actor, action, before/after
- correct notifications dispatched (Notification::fake())
