<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\UserStatus;
use App\Support\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $phone_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int $kyc_tier
 * @property UserStatus $status
 * @property CarbonImmutable|null $frozen_at
 * @property string|null $date_of_birth
 * @property string|null $nationality
 * @property string $residence_country
 * @property string $timezone
 * @property string $locale
 * @property string $preferred_fiat
 * @property string $theme
 * @property string|null $anti_phishing_code
 * @property string $referral_code
 * @property int|null $referred_by_id
 * @property CarbonImmutable|null $withdrawal_locked_until
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 * @property CarbonImmutable $created_at
 */
final class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPublicUlid;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'residence_country',
        'nationality',
        'timezone',
        'locale',
        'preferred_fiat',
        'theme',
        'referral_code',
        'referred_by_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'anti_phishing_code',
        'date_of_birth',
    ];

    protected $attributes = [
        'status' => 'active',
        'kyc_tier' => 0,
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'phone_verified_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'frozen_at' => 'immutable_datetime',
            'withdrawal_locked_until' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'kyc_tier' => 'integer',
            'date_of_birth' => 'encrypted',
            'anti_phishing_code' => 'encrypted',
        ];
    }

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<LoginAttempt, $this>
     */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function isFrozen(): bool
    {
        return $this->status === UserStatus::Frozen;
    }

    public function withdrawalsLocked(): bool
    {
        return $this->withdrawal_locked_until !== null && $this->withdrawal_locked_until->isFuture();
    }

    /**
     * Moves the account to a new status, refusing illegal moves (e.g. reopening a closed account).
     */
    public function transitionTo(UserStatus $to): void
    {
        $this->status->assertCanTransitionTo($to);
        $this->status = $to;
        $this->frozen_at = $to === UserStatus::Frozen ? CarbonImmutable::now() : null;
    }

    /**
     * Extends (never shortens) the withdrawal lock.
     */
    public function lockWithdrawalsFor(int $hours): void
    {
        $until = CarbonImmutable::now()->addHours($hours);

        if ($this->withdrawal_locked_until === null || $this->withdrawal_locked_until->lt($until)) {
            $this->withdrawal_locked_until = $until;
        }
    }

    public function routeNotificationForSms(): string
    {
        return $this->phone;
    }
}
