<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\SessionStore;
use App\Domain\Identity\Rules\PasswordRules;
use App\Domain\Identity\Services\SecurityEvents;
use App\Domain\Identity\Services\StepUpVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ChangePassword
{
    public function __construct(
        private readonly StepUpVerifier $stepUp,
        private readonly SessionStore $sessions,
        private readonly SecurityEvents $events,
    ) {}

    public function handle(User $user, StepUpCodes $codes, string $currentPassword, string $password, string $confirmation, ?string $keepSessionId): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('Your current password is not correct.')]);
        }

        Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => PasswordRules::forNewPassword([$user->email, $user->name])],
        )->validate();

        if (Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => __('Choose a password you haven\'t used for this account.')]);
        }

        $this->stepUp->verify($user, $codes->totp, $codes->email);

        $user->forceFill(['password' => $password])->save();
        $this->sessions->deleteAllExcept($user, $keepSessionId);
        $this->events->record($user, SecurityEvent::PasswordChanged);
    }
}
