<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property string $ip
 * @property string|null $user_agent
 * @property bool $success
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 */
final class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'ip', 'user_agent', 'success', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }
}
