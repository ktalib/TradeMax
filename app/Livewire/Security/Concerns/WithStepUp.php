<?php

declare(strict_types=1);

namespace App\Livewire\Security\Concerns;

use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\StepUpVerifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Shared step-up form state for security components: an authenticator code plus a code
 * we email on request. The domain actions verify both; this only collects them.
 */
trait WithStepUp
{
    public string $totpCode = '';

    public string $emailCode = '';

    public bool $emailCodeSent = false;

    public function sendStepUpCode(StepUpVerifier $stepUp): void
    {
        try {
            $stepUp->sendEmailCode($this->currentUser());
        } catch (ValidationException $e) {
            $this->addError('email_code', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->emailCodeSent = true;
        $this->dispatch('toast', message: __('We emailed you a 6-digit code.'));
    }

    protected function stepUpCodes(): StepUpCodes
    {
        return new StepUpCodes(
            $this->totpCode !== '' ? $this->totpCode : null,
            $this->emailCode !== '' ? $this->emailCode : null,
        );
    }

    protected function resetStepUp(): void
    {
        $this->reset('totpCode', 'emailCode', 'emailCodeSent');
    }

    /**
     * Livewire actions all post to one endpoint, so security actions are throttled here.
     */
    protected function throttle(string $action, int $perMinute = 6): void
    {
        $key = 'security:'.$action.':'.$this->currentUser()->id;

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw ValidationException::withMessages([
                'totp_code' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        RateLimiter::hit($key, 60);
    }

    protected function currentUser(): User
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
