<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $fingerprint_hash
 * @property string|null $user_agent
 * @property string|null $ip
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $trusted_at
 * @property CarbonImmutable|null $trusted_until
 */
final class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint_hash',
        'user_agent',
        'ip',
        'first_seen_at',
        'last_seen_at',
        'verified_at',
        'trusted_at',
        'trusted_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'trusted_at' => 'immutable_datetime',
            'trusted_until' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTrusted(): bool
    {
        return $this->trusted_until !== null && $this->trusted_until->isFuture();
    }

    /**
     * A short human label from the user agent, e.g. "Windows · Chrome".
     */
    public function label(): string
    {
        $ua = (string) $this->user_agent;

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => __('Unknown device'),
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        return $browser === null ? $os : $os.' · '.$browser;
    }
}
