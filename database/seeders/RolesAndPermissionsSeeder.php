<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff roles on the `admin` guard. Each later phase adds its permissions here
 * (ledger, deposits, withdrawals, KYC review, ...).
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    private const GUARD = 'admin';

    /** @var array<string, list<string>> role => permissions ('*' = all) */
    private const ROLES = [
        'super_admin' => ['*'],
        'admin' => ['users.view', 'users.freeze', 'users.unfreeze', 'audit.view', 'settings.view'],
        'compliance' => ['users.view', 'users.freeze', 'users.unfreeze', 'audit.view'],
        'finance' => ['users.view', 'audit.view'],
        'support_lead' => ['users.view', 'users.freeze', 'users.reset_two_factor'],
        'support_agent' => ['users.view', 'users.freeze'],
        'read_only' => ['users.view', 'audit.view', 'settings.view'],
    ];

    private const PERMISSIONS = [
        'users.view',
        'users.freeze',
        'users.unfreeze',
        'users.reset_two_factor',
        'audit.view',
        'settings.view',
        'settings.manage',
        'roles.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, self::GUARD);
            $role->syncPermissions($permissions === ['*'] ? self::PERMISSIONS : $permissions);
        }
    }
}
