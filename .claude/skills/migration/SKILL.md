---
name: migration
description: Use when creating or altering database tables.
---
- Amounts DECIMAL(36,18) unsigned where negative impossible; rates DECIMAL(10,6).
- ULIDs (`ulid('public_id')`) for any id exposed in URLs/APIs; bigint auto-increment internally.
- Index: every FK, every status, (user_id, created_at), and any column used in WHERE by a scheduled job.
- UNIQUE on idempotency_key, provider tx ids, tx_hash+network, receipt sha256.
- Timestamps in UTC; display converted to the user's timezone (auto-detected from browser at signup, editable).
- Every migration has a working down() method.
