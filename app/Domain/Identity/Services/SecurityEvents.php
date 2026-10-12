<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Events\SecurityEventOccurred;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\SecurityAlertNotification;
use App\Support\Audit\AuditLogger;
use App\Support\Privacy\Mask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * One place that applies the consequences of a security event: withdrawal lock where the
 * rules require it, an audit row, an alert to the account owner (and to a previous
 * contact when it was the contact that changed), and a domain event for other modules.
 */
final class SecurityEvents
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ?Request $request = null,
    ) {}

    /**
     * @param  array<string, string>  $context  extra, non-secret details shown in the alert
     * @param  array<string, string>  $alsoNotify  previous contacts: ['mail' => old email] and/or ['sms' => old phone]
     */
    public function record(User $user, SecurityEvent $event, array $context = [], array $alsoNotify = []): void
    {
        if ($event->locksWithdrawals()) {
            $user->lockWithdrawalsFor((int) config('platform.withdrawals.security_change_lock_hours', 24));
            $user->save();
        }

        $context += [
            'ip' => Mask::ip($this->request?->ip()),
            'time' => now()->toIso8601String(),
        ];

        $this->audit->record('security.'.$event->value, $user, after: $context);

        $user->notify(new SecurityAlertNotification($user, $event, $context));

        foreach ($alsoNotify as $channel => $route) {
            Notification::route($channel, $route)->notify(new SecurityAlertNotification($user, $event, $context, onlyChannel: $channel));
        }

        SecurityEventOccurred::dispatch($user, $event, $context);
    }
}
