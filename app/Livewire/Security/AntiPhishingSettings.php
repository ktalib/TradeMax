<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Domain\Identity\Actions\SetAntiPhishingCode;
use App\Livewire\Security\Concerns\WithStepUp;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class AntiPhishingSettings extends Component
{
    use WithStepUp;

    public bool $editing = false;

    public string $anti_phishing_code = '';

    public function open(): void
    {
        $this->editing = true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('editing', 'anti_phishing_code');
        $this->resetStepUp();
    }

    public function save(SetAntiPhishingCode $action): void
    {
        $this->throttle('anti-phishing');
        $action->handle($this->currentUser(), $this->totpCode, $this->anti_phishing_code);

        $this->cancel();
        $this->dispatch('toast', message: __('Anti-phishing code saved. It now appears at the top of our emails.'));
    }

    public function render(): View
    {
        return view('livewire.security.anti-phishing-settings', [
            'current' => $this->currentUser()->anti_phishing_code,
        ]);
    }
}
