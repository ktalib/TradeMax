<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Domain\Identity\Actions\FreezeAccount;
use App\Livewire\Security\Concerns\WithStepUp;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class FreezeAccountCard extends Component
{
    use WithStepUp;

    public bool $confirming = false;

    public function freeze(FreezeAccount $action): void
    {
        $action->handle($this->currentUser(), 'security_page', session()->getId());

        $this->redirectRoute('account.frozen');
    }

    public function render(): View
    {
        return view('livewire.security.freeze-account-card');
    }
}
