<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Domain\Compliance\Repositories\Contracts\CountryDirectory;
use App\Domain\Identity\Actions\ConfirmPhoneChange;
use App\Domain\Identity\Actions\RequestEmailChange;
use App\Domain\Identity\Actions\RequestPhoneChange;
use App\Livewire\Security\Concerns\WithStepUp;
use App\Support\Privacy\Mask;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ContactSettings extends Component
{
    use WithStepUp;

    /** null | 'email' | 'phone' | 'phone-code' */
    public ?string $editing = null;

    public string $email = '';

    public string $phone = '';

    public string $phone_country = '';

    public string $code = '';

    public function mount(): void
    {
        $this->phone_country = $this->currentUser()->residence_country;
    }

    public function edit(string $what): void
    {
        $this->editing = in_array($what, ['email', 'phone'], true) ? $what : null;
        $this->reset('email', 'phone', 'code');
        $this->resetStepUp();
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetStepUp();
    }

    public function requestEmail(RequestEmailChange $action): void
    {
        $this->throttle('email-change');
        $action->handle($this->currentUser(), $this->stepUpCodes(), $this->email);

        $this->editing = null;
        $this->resetStepUp();
        $this->dispatch('toast', message: __('Check your new inbox and open the link while signed in to finish.'));
    }

    public function requestPhone(RequestPhoneChange $action): void
    {
        $this->throttle('phone-change');
        $action->handle($this->currentUser(), $this->stepUpCodes(), $this->phone, $this->phone_country);

        $this->editing = 'phone-code';
        $this->resetStepUp();
    }

    public function confirmPhone(ConfirmPhoneChange $action): void
    {
        $this->throttle('phone-confirm', 10);
        $action->handle($this->currentUser(), $this->code);

        $this->editing = null;
        $this->reset('code', 'phone');
        $this->dispatch('toast', message: __('Phone number changed. Withdrawals are paused for 24 hours.'));
    }

    public function render(CountryDirectory $countries): View
    {
        $user = $this->currentUser();

        return view('livewire.security.contact-settings', [
            'maskedEmail' => Mask::email($user->email),
            'maskedPhone' => Mask::phone($user->phone),
            'countries' => $countries->registrable(),
        ]);
    }
}
