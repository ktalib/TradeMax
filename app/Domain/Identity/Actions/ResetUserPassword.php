<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\SessionStore;
use App\Domain\Identity\Rules\PasswordRules;
use App\Domain\Identity\Services\SecurityEvents;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Password reset by email link (Fortify). Signs out every session and pauses withdrawals
 * for 24 hours, because whoever can read the inbox can reset the password.
 */
final class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly SecurityEvents $events,
    ) {}

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        if (! $user instanceof User) {
            throw new InvalidArgumentException('Expected an Identity user.');
        }

        Validator::make($input, [
            'password' => PasswordRules::forNewPassword([$user->email, $user->name]),
        ])->validate();

        $user->forceFill(['password' => (string) $input['password']])->save();
        $this->sessions->deleteAllExcept($user, null);
        $this->events->record($user, SecurityEvent::PasswordReset);
    }
}
