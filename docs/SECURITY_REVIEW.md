# Security Review — TradeMax

> Run at the end of every phase and before any release (see `security-review` skill). Record results per phase.

## Checklist

- [ ] Mass assignment: every model uses `$fillable`, no `$guarded = []`.
- [ ] Authorization: every controller/Livewire action calls a Policy or Gate.
- [ ] IDOR: route model binding scoped to auth user where applicable.
- [ ] CSRF on all forms; webhooks excluded and signature-verified.
- [ ] Rate limits on login, register, OTP, password reset, orders, withdrawals, ticket creation.
- [ ] Uploads: MIME sniffed, re-encoded, private disk, size cap.
- [ ] Secrets only from env; `.env.example` has no real values.
- [ ] Headers: CSP, HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy (`SecurityHeaders` middleware).
- [ ] Dependencies: `composer audit` && `npm audit` with zero high/critical.
- [ ] Full `LedgerInvariantTest` green.

## Findings

_(append per phase)_

### Phase 1 — Identity & Security (2026-10-12)

| Check | Result |
|---|---|
| Mass assignment | All models use `$fillable`; status, 2FA, verification and lock fields are only set with `forceFill` inside actions. No `$guarded = []`. |
| Authorization | Owner checks in `RevokeApiKey`, `ForgetDevice`, `SignOutSession` (session queries are scoped by user_id); `ConfirmEmailChange` checks the change belongs to the signed-in user. Tested. |
| IDOR | Public ULIDs in URLs (`api_keys`, `contact_changes`, users in freeze links). Freeze links are signed and expire in 7 days. |
| CSRF | All forms carry `@csrf`; Livewire requests are CSRF-protected. No webhooks yet. |
| Rate limits | login (5/min per email+IP, 20/h per email), 2FA challenge 5/min, register 5/h per IP, password reset 3/min, OTP send/verify, freeze link, Livewire security actions (6/min), OTP issue (3 per 10 min per purpose). Tested for login and register. |
| Uploads | None in Phase 1. |
| Secrets | `.env.example` has no real values. 2FA secrets, recovery codes, DOB, anti-phishing code, API secrets and pending contact values are encrypted at rest. OTPs are stored as HMACs. |
| Headers | `SecurityHeaders`: CSP with nonce (+ `'unsafe-eval'` for Alpine, ADR-0007), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, COOP, HSTS on HTTPS. Tested. |
| Dependencies | `composer audit`: no advisories. |
| Ledger invariants | Not applicable until Phase 2. |
| Extra | Timing-equalised login for unknown emails; generic "credentials" error; codes never in SMS links; SMS ≤ 160 chars; emails carry the anti-phishing code and a freeze link; one-time secrets never enter Livewire's payload; audit log is append-only. |

**Open items:** passkeys are off until their UI ships; the admin panel (and admin 2FA enforcement) arrives with Filament; the DB-level UPDATE/DELETE revoke on `audit_logs` must be applied at deployment (RUNBOOK).
