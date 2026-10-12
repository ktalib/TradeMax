<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SecurityEvents;
use App\Domain\Identity\Services\StepUpVerifier;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

final class RegenerateRecoveryCodes
{
    public function __construct(
        private readonly StepUpVerifier $stepUp,
        private readonly GenerateNewRecoveryCodes $generate,
        private readonly SecurityEvents $events,
    ) {}

    /**
     * @return list<string> the new codes, to show once
     */
    public function handle(User $user, StepUpCodes $codes): array
    {
        $this->stepUp->verify($user, $codes->totp, $codes->email);

        ($this->generate)($user);
        $user->refresh();

        $this->events->record($user, SecurityEvent::RecoveryCodesRegenerated);

        return array_values(array_map('strval', $user->recoveryCodes()));
    }
}
