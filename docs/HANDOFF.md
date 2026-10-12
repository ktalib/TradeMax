# Handoff — TradeMax

> Produced at the end of Phase 12. Must cover: what was built, how to run it, all env keys needed for production, known limitations, and the go-live checklist.

## Go-live checklist

- [ ] Regulatory registration confirmed in home jurisdiction and every served country
- [ ] Custody set up (`CUSTODY_DRIVER`; app holds no private keys)
- [ ] Real provider keys configured (payments, chain, SMS, KYC, push, FX, email)
- [ ] Backups verified (restore test passed)
- [ ] Pen-test scheduled / completed
- [ ] `docs/SECURITY_REVIEW.md` fully signed off
- [ ] `LedgerInvariantTest` + reconciliation green
- [ ] `PLATFORM_LIVE_MODE` flip procedure documented and rehearsed

_(content added as the build completes)_
