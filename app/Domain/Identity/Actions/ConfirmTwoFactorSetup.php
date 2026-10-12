<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SecurityEvents;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

/**
 * Confirms the authenticator app during onboarding (or after a reset) with its first code.
 */
final class ConfirmTwoFactorSetup
{
    public function __construct(
        private readonly ConfirmTwoFactorAuthentication $confirm,
        private readonly SecurityEvents $events,
    ) {}

    public function handle(User $user, string $code): void
    {
        try {
            ($this->confirm)($user, preg_replace('/\D/', '', $code) ?? '');
        } catch (ValidationException) {
            throw ValidationException::withMessages(['code' => __('That code didn\'t match. Check your device\'s clock is set automatically, then enter the current code.')]);
        }

        $this->events->record($user->refresh(), SecurityEvent::TwoFactorEnabled);
    }
}
