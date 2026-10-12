<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SecurityEvents;
use App\Domain\Identity\Services\StepUpVerifier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A phrase only the user knows, printed at the top of every email we send, so a phishing
 * email (which can't know it) stands out. Changing it needs an authenticator code.
 */
final class SetAntiPhishingCode
{
    public function __construct(
        private readonly StepUpVerifier $stepUp,
        private readonly SecurityEvents $events,
    ) {}

    public function handle(User $user, string $totpCode, string $phrase): void
    {
        $phrase = trim($phrase);

        Validator::make(['anti_phishing_code' => $phrase], [
            'anti_phishing_code' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[\pL\pN\- ]+$/u'],
        ], [
            'anti_phishing_code.regex' => __('Use letters, numbers, spaces or hyphens only.'),
        ])->validate();

        if (! $this->stepUp->verifyTotp($user, $totpCode)) {
            throw ValidationException::withMessages(['totp_code' => __('That authenticator code is not valid. Enter the current 6-digit code from your app.')]);
        }

        $user->forceFill(['anti_phishing_code' => $phrase])->save();

        $this->events->record($user, SecurityEvent::AntiPhishingCodeChanged);
    }
}
