<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\ContactType;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\ContactChange;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SecurityEvents;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies a pending email change once the link sent to the new address is opened by the
 * signed-in owner. Alerts go to both the old and the new address.
 */
final class ConfirmEmailChange
{
    public function __construct(private readonly SecurityEvents $events) {}

    public function handle(User $user, ContactChange $change): void
    {
        if ($change->user_id !== $user->id || $change->type !== ContactType::Email || ! $change->isPending()) {
            throw ValidationException::withMessages(['email' => __('This link has expired or was already used. Start the email change again from the security page.')]);
        }

        if (User::query()->where('email', $change->new_value)->exists()) {
            throw ValidationException::withMessages(['email' => __('That email address is now used by another account.')]);
        }

        $oldEmail = $user->email;

        DB::transaction(function () use ($user, $change): void {
            $now = CarbonImmutable::now();
            $user->forceFill(['email' => $change->new_value, 'email_verified_at' => $now])->save();
            $change->forceFill(['confirmed_at' => $now])->save();
        });

        $this->events->record($user, SecurityEvent::EmailChanged, alsoNotify: ['mail' => $oldEmail]);
    }
}
