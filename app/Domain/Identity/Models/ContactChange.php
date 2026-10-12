<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\ContactType;
use App\Support\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending email or phone change. The new value only replaces the old one after
 * it has been proven (link to the new email, or SMS code to the new phone).
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property ContactType $type
 * @property string $new_value
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 */
final class ContactChange extends Model
{
    use HasPublicUlid;

    protected $fillable = ['user_id', 'type', 'new_value', 'expires_at'];

    protected $hidden = ['new_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'new_value' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->confirmed_at === null && $this->cancelled_at === null && $this->expires_at->isFuture();
    }
}
