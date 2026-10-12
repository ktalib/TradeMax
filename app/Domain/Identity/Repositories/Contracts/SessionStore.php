<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories\Contracts;

use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;

interface SessionStore
{
    /**
     * @return list<array{id: string, ip: string|null, user_agent: string|null, last_active: CarbonImmutable}>
     */
    public function forUser(User $user): array;

    public function delete(User $user, string $sessionId): bool;

    /**
     * @return int number of sessions ended
     */
    public function deleteAllExcept(User $user, ?string $keepSessionId): int;
}
