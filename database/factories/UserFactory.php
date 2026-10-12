<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/**
 * Default state: a fully onboarded user (email + phone verified, authenticator confirmed),
 * because that is what most feature tests need. Use the states to step back.
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    public const PASSWORD = 'correct-horse-battery-staple-42';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $secret = (new Google2FA)->generateSecretKey();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Valid UK mobile format; tests use the fake SMS driver, so nothing is ever sent.
            'phone' => '+447911'.str_pad((string) fake()->unique()->numberBetween(0, 999_999), 6, '0', STR_PAD_LEFT),
            'phone_verified_at' => now(),
            'password' => self::$password ??= Hash::make(self::PASSWORD),
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode([
                'RCVR1-ALPHA', 'RCVR2-BRAVO', 'RCVR3-CHARL', 'RCVR4-DELTA',
            ])),
            'two_factor_confirmed_at' => now(),
            'residence_country' => 'GB',
            'timezone' => 'UTC',
            'locale' => 'en',
            'preferred_fiat' => 'USD',
            'referral_code' => strtoupper(Str::random(8)),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withoutVerifiedPhone(): static
    {
        return $this->state(fn () => ['phone_verified_at' => null]);
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Frozen, 'frozen_at' => now()]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }
}
