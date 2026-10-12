<?php

declare(strict_types=1);

namespace App\Domain\Admin\Console;

use App\Domain\Admin\Models\Admin;
use App\Domain\Identity\Rules\PasswordRules;
use App\Support\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Creates a staff account. The password is asked for interactively, never passed as an
 * argument, so it doesn't end up in shell history.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email} {--name=} {--role=super_admin}';

    protected $description = 'Create a back-office staff account';

    public function handle(AuditLogger $audit): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $name = (string) ($this->option('name') ?: $this->ask('Full name'));
        $role = (string) $this->option('role');

        if (! Role::query()->where('name', $role)->where('guard_name', 'admin')->exists()) {
            $this->error("Role \"{$role}\" does not exist. Run the seeders first (composer fresh or php artisan db:seed).");

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (min 10 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            ['email' => ['required', 'email', 'unique:admins,email'], 'name' => ['required', 'string', 'max:120'], 'password' => PasswordRules::forNewPassword([$email, $name])],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $admin = Admin::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
        $admin->assignRole($role);

        $audit->record('admin.created', $admin, after: ['role' => $role]);

        $this->info("Created {$role} {$email}. Two-step verification is set up at first sign-in to the admin panel.");

        return self::SUCCESS;
    }
}
