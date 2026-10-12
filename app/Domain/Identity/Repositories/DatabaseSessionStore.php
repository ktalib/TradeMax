<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\SessionStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Reads Laravel's `sessions` table (SESSION_DRIVER=database is required for the
 * sessions list and remote sign-out).
 */
final class DatabaseSessionStore implements SessionStore
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function forUser(User $user): array
    {
        $rows = $this->db->table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        $sessions = [];
        foreach ($rows as $row) {
            $sessions[] = [
                'id' => (string) $row->id,
                'ip' => is_string($row->ip_address) ? $row->ip_address : null,
                'user_agent' => is_string($row->user_agent) ? $row->user_agent : null,
                'last_active' => CarbonImmutable::createFromTimestamp((int) $row->last_activity),
            ];
        }

        return $sessions;
    }

    public function delete(User $user, string $sessionId): bool
    {
        return $this->db->table('sessions')->where('user_id', $user->id)->where('id', $sessionId)->delete() === 1;
    }

    public function deleteAllExcept(User $user, ?string $keepSessionId): int
    {
        return $this->db->table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
