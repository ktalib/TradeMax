# TradeMax

Custodial crypto exchange and investment platform: spot trading, deposits and withdrawals, staking, copy trading, tiered KYC and an admin back office. Built with Laravel 12, Livewire 3 and a bcmath-backed double-entry ledger.

## Quick start (Windows)

```powershell
composer setup   # .env, dependencies, app key, migrations, assets
composer dev     # app server, queue worker, scheduler and Vite
```

Then open <http://localhost:8000/dev/ui> for the component gallery. Prerequisites and details are in [docs/SETUP.md](docs/SETUP.md).

## Everyday commands

| Command | What it does |
|---|---|
| `composer test` | Pest suite |
| `composer analyse` | Larastan level 8 |
| `composer lint` / `composer format` | Check / fix formatting with Pint |
| `composer rectify` | Rector refactors |
| `composer fresh` | Rebuild and seed the database |

## Where things are

- `docs/PLAN.md`: phases, deliverables and exit gates
- `docs/DECISIONS.md`: architecture decisions (ADRs)
- `docs/PROGRESS.md`: phase status
- `AGENTS.md`: engineering rules (money, architecture, security, testing)
- `app/Domain/<Module>`: business modules
- `app/Support/Money`: `Decimal` and `DecimalFormatter`; all money math goes through these
- `resources/views/components/ui`: the UI component library
