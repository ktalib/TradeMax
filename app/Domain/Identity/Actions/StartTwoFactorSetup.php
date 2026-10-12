<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use LogicException;

/**
 * Creates the authenticator secret (unconfirmed) so the setup page can show its QR code.
 * Reloading the page keeps the same secret until it is confirmed.
 */
final class StartTwoFactorSetup
{
    public function __construct(private readonly EnableTwoFactorAuthentication $enable) {}

    public function handle(User $user): void
    {
        if ($user->hasConfirmedTwoFactor()) {
            throw new LogicException('Two-factor authentication is already confirmed for this user.');
        }

        if ($user->two_factor_secret === null) {
            ($this->enable)($user);
        }
    }
}
