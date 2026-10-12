<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDevice;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Removes trust from a device: its next sign-in needs the emailed code again.
 * The row stays for the security history.
 */
final class ForgetDevice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, UserDevice $device): void
    {
        if ($device->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $device->forceFill(['verified_at' => null, 'trusted_at' => null, 'trusted_until' => null])->save();

        $this->audit->record('user.device_forgotten', $device, actor: $user);
    }
}
