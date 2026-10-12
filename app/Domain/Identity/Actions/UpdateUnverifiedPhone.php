<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PhoneNumbers;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Fixes a typo in the phone number during onboarding. Once a number is verified it can
 * only change through RequestPhoneChange (step-up + 24h withdrawal lock).
 */
final class UpdateUnverifiedPhone
{
    public function __construct(
        private readonly PhoneNumbers $phones,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, string $input, string $region): void
    {
        if ($user->hasVerifiedPhone()) {
            throw new LogicException('A verified phone number must be changed through RequestPhoneChange.');
        }

        $phone = $this->phones->toE164($input, $region);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid mobile number. Landlines can\'t receive our security codes.')]);
        }

        if (User::query()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __('This phone number is already linked to an account.')]);
        }

        $user->forceFill(['phone' => $phone])->save();

        $this->audit->record('user.unverified_phone_updated', $user);
    }
}
