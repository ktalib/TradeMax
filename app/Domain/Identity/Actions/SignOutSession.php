<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\SessionStore;
use App\Support\Audit\AuditLogger;

/**
 * Ends one other session, or (with $sessionId = null) every session except the current one.
 */
final class SignOutSession
{
    public function __construct(
        private readonly SessionStore $sessions,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, ?string $sessionId, string $currentSessionId): int
    {
        if ($sessionId === $currentSessionId) {
            return 0;
        }

        $ended = $sessionId === null
            ? $this->sessions->deleteAllExcept($user, $currentSessionId)
            : (int) $this->sessions->delete($user, $sessionId);

        $this->audit->record('user.sessions_signed_out', $user, after: ['count' => $ended]);

        return $ended;
    }
}
