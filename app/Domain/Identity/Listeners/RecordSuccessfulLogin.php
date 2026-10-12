<?php

declare(strict_types=1);

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DeviceRecognizer;
use App\Domain\Identity\Services\SecurityEvents;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Runs after every successful sign-in (password, 2FA challenge or remember-me cookie):
 * records the attempt, recognises the device, decides whether this session must pass the
 * new-device email check, and alerts the user about new devices and new IPs.
 */
final class RecordSuccessfulLogin
{
    public function __construct(
        private readonly DeviceRecognizer $devices,
        private readonly SecurityEvents $events,
        private readonly Request $request,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;

        LoginAttempt::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => (string) $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
            'success' => true,
            'reason' => $event->remember ? 'remember' : null,
        ]);

        $user->forceFill(['last_login_at' => CarbonImmutable::now(), 'last_login_ip' => $this->request->ip()])->save();

        $sighting = $this->devices->sight($user, $this->request);

        if ($this->request->hasSession()) {
            if ($sighting->needsVerification()) {
                $this->request->session()->forget(DeviceRecognizer::SESSION_KEY);
            } else {
                $this->request->session()->put(DeviceRecognizer::SESSION_KEY, $sighting->device->id);
            }
        }

        if ($sighting->warrantsAlert()) {
            $this->events->record($user, SecurityEvent::NewDeviceLogin, ['device' => $sighting->device->label()]);
        }
    }
}
