---
name: security-review
description: Use at the end of every phase and before any release.
---
Checklist (write results to docs/SECURITY_REVIEW.md):
- Mass assignment: every model uses $fillable, no $guarded = [].
- Authorization: every controller/Livewire action calls a Policy or Gate.
- IDOR: route model binding scoped to auth user where applicable.
- CSRF on all forms; webhooks excluded and signature-verified.
- Rate limits on login, register, OTP, password reset, orders, withdrawals, ticket creation.
- Uploads: MIME sniffed, re-encoded, private disk, size cap.
- Secrets only from env; .env.example has no real values.
- Headers: CSP, HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy (middleware SecurityHeaders).
- Dependencies: composer audit && npm audit with zero high/critical.
- Run the full LedgerInvariantTest.
