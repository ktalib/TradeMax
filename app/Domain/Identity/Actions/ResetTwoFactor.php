<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SecurityEvents;
use App\Domain\Identity\Services\StepUpVerifier;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Removes the current authenticator (e.g. moving to a new phone). Two-step verification is
 * mandatory, so the user is sent straight to set up a new one; withdrawals pause for 24h.
 */
final class ResetTwoFactor
{
    public function __construct(
        private readonly StepUpVerifier $stepUp,
        private readonly DisableTwoFactorAuthentication $disable,
        private readonly SecurityEvents $events,
    ) {}

    public function handle(User $user, StepUpCodes $codes): void
    {
        $this->stepUp->verify($user, $codes->totp, $codes->email);

        ($this->disable)($user);

        $this->events->record($user->refresh(), SecurityEvent::TwoFactorDisabled);
    }
}
