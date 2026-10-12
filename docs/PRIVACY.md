# Privacy — TradeMax

> Data-processing register (multi-regime: GDPR/UK GDPR/NDPA/POPIA-style). Expand as compliance features are built.

## Data categories & lawful basis

| Category | Examples | Storage | Retention |
|---|---|---|---|
| Account | name, email, phone, locale, timezone | `users` (secrets encrypted) | account lifetime |
| Identity (KYC) | ID docs, selfie, liveness, doc-number hash | private disk, encrypted at rest | `KYC_RETENTION_YEARS` |
| Financial | journals, orders, trades, withdrawals | ledger tables (append-only) | legally required |
| Security | devices, login attempts, audit logs | append-only | per policy |
| Consents | document version, jurisdiction, timestamp | `consents` | account lifetime |

## Data-subject rights

- Export (JSON/CSV), rectification, and erasure handled as account closure with legally required retention kept separate.
- Cookie consent banner (essential/analytics/marketing) where required by residence country.

## Sub-processors

_(payments, chain verifiers, SMS, KYC, push, FX, custody — list with DPAs on go-live)_
