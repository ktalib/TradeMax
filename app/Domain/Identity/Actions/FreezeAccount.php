<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\ApiKey;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\SessionStore;
use App\Domain\Identity\Services\SecurityEvents;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * "I've been hacked": no codes required, by design. It only ever makes the account safer.
 * Stops withdrawals and API keys at once and signs out every other session; open orders
 * are cancelled by Trading listening for the AccountFrozen security event.
 */
final class FreezeAccount
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly SecurityEvents $events,
    ) {}

    /**
     * @param  string  $source  where the freeze came from: security_page | email_link | staff
     */
    public function handle(User $user, string $source, ?string $keepSessionId = null): void
    {
        if ($user->status === UserStatus::Frozen) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $user->transitionTo(UserStatus::Frozen);
            $user->save();

            ApiKey::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => CarbonImmutable::now()]);
        });

        $this->sessions->deleteAllExcept($user, $keepSessionId);

        $this->events->record($user, SecurityEvent::AccountFrozen, ['source' => $source]);
    }
}
