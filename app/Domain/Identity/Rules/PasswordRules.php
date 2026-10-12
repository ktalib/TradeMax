<?php

declare(strict_types=1);

namespace App\Domain\Identity\Rules;

use Illuminate\Validation\Rules\Password;

final class PasswordRules
{
    /**
     * Rules for any new password: length, strength, and not in a known breach (checked
     * against Have I Been Pwned by k-anonymity; only a 5-character hash prefix leaves the server).
     *
     * @param  list<string>  $userInputs
     * @return list<mixed>
     */
    public static function forNewPassword(array $userInputs = []): array
    {
        $rule = Password::min((int) config('platform.security.password_min_length', 10))->max(128);

        if ((bool) config('platform.security.check_breached_passwords', true)) {
            $rule = $rule->uncompromised();
        }

        return ['required', 'string', 'confirmed', $rule, new StrongPassword($userInputs)];
    }
}
