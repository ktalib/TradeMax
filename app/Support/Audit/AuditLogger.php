<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Domain\Admin\Models\Admin;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes one append-only audit row per security- or money-relevant action.
 * Callers pass only the fields that changed; secrets never go into before/after.
 */
final class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?Authenticatable $actor = null,
    ): AuditLog {
        $actor ??= $this->request?->user();

        return AuditLog::query()->create([
            'actor_type' => match (true) {
                $actor instanceof Admin => 'admin',
                $actor instanceof User => 'user',
                default => 'system',
            },
            'actor_id' => $actor instanceof Model ? $actor->getKey() : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip' => $this->request?->ip(),
            'user_agent' => $this->request !== null ? mb_substr((string) $this->request->userAgent(), 0, 512) : null,
        ]);
    }
}
