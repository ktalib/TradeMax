<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\ApiKeyPermission;
use App\Support\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $name
 * @property string $key_prefix
 * @property string $key_hash
 * @property string $secret
 * @property Collection<int, ApiKeyPermission> $permissions
 * @property list<string>|null $ip_whitelist
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable $created_at
 */
final class ApiKey extends Model
{
    use HasPublicUlid;

    protected $fillable = ['user_id', 'name', 'key_prefix', 'key_hash', 'secret', 'permissions', 'ip_whitelist'];

    protected $hidden = ['key_hash', 'secret'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'permissions' => AsEnumCollection::of(ApiKeyPermission::class),
            'ip_whitelist' => 'array',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
