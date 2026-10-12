<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only. The model refuses updates and deletes; in production the database user
 * also has no UPDATE/DELETE grant on this table (docs/RUNBOOK.md).
 *
 * @property int $id
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
final class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip', 'user_agent'];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Audit log entries are immutable.'));
        self::deleting(static fn (): never => throw new LogicException('Audit log entries cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
