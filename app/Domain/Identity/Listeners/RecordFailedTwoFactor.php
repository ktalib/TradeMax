<?php

declare(strict_types=1);

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

final class RecordFailedTwoFactor
{
    public function __construct(private readonly Request $request) {}

    public function handle(TwoFactorAuthenticationFailed $event): void
    {
        $user = $event->user;

        LoginAttempt::query()->create([
            'user_id' => $user instanceof User ? $user->id : null,
            'email' => $user instanceof User ? $user->email : '',
            'ip' => (string) $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
            'success' => false,
            'reason' => 'two_factor_failed',
        ]);
    }
}
