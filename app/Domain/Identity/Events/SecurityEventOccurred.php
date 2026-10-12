<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Other modules react to account security changes through this event (e.g. Trading
 * cancels open orders when an account is frozen) instead of reading Identity tables.
 */
final class SecurityEventOccurred
{
    use Dispatchable;

    /**
     * @param  array<string, string>  $context
     */
    public function __construct(
        public readonly User $user,
        public readonly SecurityEvent $event,
        public readonly array $context = [],
    ) {}
}
