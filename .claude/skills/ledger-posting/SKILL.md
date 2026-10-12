---
name: ledger-posting
description: Use whenever code credits, debits, locks, unlocks or transfers any user or system balance.
---
1. Build a `JournalDTO` with: reference_type, reference_id, idempotency_key, description, and >= 2 `EntryDTO`s.
2. Each entry: wallet_id, asset_id, direction (debit|credit), amount (Decimal, > 0), balance_type (available|locked).
3. Call `LedgerService::post($journal)`. It: begins transaction → checks idempotency (replay returns the existing journal) → locks user wallets ordered by id → validates per-asset balance (debits == credits) and the sign convention → inserts journal + entries → updates wallet balance columns (system wallets via atomic increment, no row lock) → commits → dispatches `JournalPosted`. It does NOT write snapshots — those are daily via `ledger:snapshot`.
   Sign convention: credit increases a wallet, debit decreases it. User wallets never < 0; `system_hot`/`system_cold`/`system_demo_faucet` may be negative (wallet type Enum `allowsNegative()`). Example deposit: debit `system_hot`, credit user `spot.available`.
4. Never touch `wallets.available`/`wallets.locked` anywhere else. A grep for `->update(['available'` outside LedgerService must return zero results.
5. Reversals: post a new journal with mirrored entries and `reverses_journal_id` set. Never edit or delete a posted journal.
6. Test: assert balanced, assert idempotent replay returns same journal, assert concurrent double-spend fails.
