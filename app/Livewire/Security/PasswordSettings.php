<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Domain\Identity\Actions\ChangePassword;
use App\Livewire\Security\Concerns\WithStepUp;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PasswordSettings extends Component
{
    use WithStepUp;

    public bool $editing = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function open(): void
    {
        $this->editing = true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('editing', 'current_password', 'password', 'password_confirmation');
        $this->resetStepUp();
    }

    public function save(ChangePassword $action): void
    {
        $this->throttle('password');

        $action->handle(
            $this->currentUser(),
            $this->stepUpCodes(),
            $this->current_password,
            $this->password,
            $this->password_confirmation,
            session()->getId(),
        );

        $this->cancel();
        $this->dispatch('toast', message: __('Password changed. Other devices were signed out and withdrawals are paused for 24 hours.'));
    }

    public function render(): View
    {
        return view('livewire.security.password-settings');
    }
}
