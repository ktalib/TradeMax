<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Domain\Identity\Actions\ForgetDevice;
use App\Domain\Identity\Actions\SignOutSession;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Repositories\Contracts\SessionStore;
use App\Domain\Identity\Services\DeviceRecognizer;
use App\Livewire\Security\Concerns\WithStepUp;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class DevicesAndSessions extends Component
{
    use WithStepUp;

    public function forget(int $deviceId, ForgetDevice $action): void
    {
        $device = UserDevice::query()->whereKey($deviceId)->firstOrFail();
        $action->handle($this->currentUser(), $device);

        $this->dispatch('toast', message: __('That device will need an email code next time it signs in.'));
    }

    public function signOut(string $sessionId, SignOutSession $action): void
    {
        $action->handle($this->currentUser(), $sessionId, session()->getId());
        $this->dispatch('toast', message: __('Signed out of that session.'));
    }

    public function signOutOthers(SignOutSession $action): void
    {
        $count = $action->handle($this->currentUser(), null, session()->getId());
        $this->dispatch('toast', message: trans_choice('Signed out of :count other session.|Signed out of :count other sessions.', $count, ['count' => $count]));
    }

    public function render(SessionStore $sessions, DeviceRecognizer $recognizer): View
    {
        $user = $this->currentUser();

        return view('livewire.security.devices-and-sessions', [
            'devices' => $user->devices()->latest('last_seen_at')->limit(20)->get(),
            'currentDeviceId' => $recognizer->current($user, request())?->id,
            'sessions' => $sessions->forUser($user),
            'currentSessionId' => session()->getId(),
            'attempts' => LoginAttempt::query()->where('user_id', $user->id)->latest('id')->limit(10)->get(),
        ]);
    }
}
