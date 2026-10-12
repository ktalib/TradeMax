<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Domain\Identity\Actions\RegenerateRecoveryCodes;
use App\Domain\Identity\Actions\ResetTwoFactor;
use App\Livewire\Security\Concerns\WithStepUp;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class TwoFactorSettings extends Component
{
    use WithStepUp;

    /** null | 'recovery' | 'reset' */
    public ?string $mode = null;

    /**
     * Shown in the regenerating response only (protected: never in Livewire's page payload).
     *
     * @var list<string>
     */
    protected array $recoveryCodes = [];

    public function start(string $mode): void
    {
        $this->mode = in_array($mode, ['recovery', 'reset'], true) ? $mode : null;
        $this->recoveryCodes = [];
        $this->resetStepUp();
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->mode = null;
        $this->resetStepUp();
    }

    public function regenerate(RegenerateRecoveryCodes $action): void
    {
        $this->throttle('recovery-codes');
        $this->recoveryCodes = $action->handle($this->currentUser(), $this->stepUpCodes());
        $this->mode = null;
        $this->resetStepUp();
    }

    public function resetAuthenticator(ResetTwoFactor $action): void
    {
        $this->throttle('reset-2fa');
        $action->handle($this->currentUser(), $this->stepUpCodes());

        $this->redirectRoute('onboarding.two-factor');
    }

    public function render(): View
    {
        return view('livewire.security.two-factor-settings', [
            'confirmedAt' => $this->currentUser()->two_factor_confirmed_at,
            'recoveryCodes' => $this->recoveryCodes,
        ]);
    }
}
