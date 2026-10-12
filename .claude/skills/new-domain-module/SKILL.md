---
name: new-domain-module
description: Use when scaffolding any new business module (e.g. Staking, CopyTrading).
---
1. Create app/Domain/<Module>/ with Models, Actions, Services, Repositories/Contracts, DTOs, Events, Listeners, Jobs, Enums, Exceptions, Policies.
2. Migration(s) with FKs, indexes on every FK + every status + created_at, UNIQUE on idempotency keys.
3. Status Enum with transition map.
4. Repository interface + Eloquent implementation, bound in DomainServiceProvider.
5. Actions (one public `handle()` each), wrapped in DB::transaction when they write.
6. Policy + permission names registered in PermissionSeeder.
7. Filament resource for admin.
8. Livewire user pages using design-system components only.
9. Pest tests: unit (Actions), feature (HTTP/Livewire), and invariants.
10. Add module section to docs/ARCHITECTURE.md.
