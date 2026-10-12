<?php

declare(strict_types=1);

use App\Domain\Compliance\Enums\CountryStatus;
use App\Domain\Compliance\Models\Country;
use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DeviceRecognizer;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\Drivers\ArraySmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
| Feature tests boot the framework on a fresh database; Unit tests stay framework-free.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Signs in a fully onboarded user with this session's device already verified.
 */
function signIn(?User $user = null): User
{
    // Reload so every column is present, as it is for a user resolved by a real login.
    $user = ($user ?? User::factory()->create())->refresh();

    test()->actingAs($user)->withSession([DeviceRecognizer::SESSION_KEY => 1]);

    return $user;
}

/**
 * The authenticator code the user's app would show right now.
 */
function totpFor(User $user): string
{
    return (new Google2FA)->getCurrentOtp((string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret));
}

/**
 * Valid step-up codes: the current authenticator code plus a freshly issued email code.
 */
function stepUpFor(User $user): StepUpCodes
{
    $emailCode = app(OtpService::class)->issue($user, OtpPurpose::StepUp, OtpChannel::Email, $user->email);

    return new StepUpCodes(totpFor($user), $emailCode);
}

function sms(): ArraySmsSender
{
    $sender = app(SmsSender::class);
    assert($sender instanceof ArraySmsSender);

    return $sender;
}

function seedCountries(): void
{
    foreach ([
        ['GB', 'United Kingdom', CountryStatus::Allowed],
        ['NG', 'Nigeria', CountryStatus::Restricted],
        ['CA', 'Canada', CountryStatus::Allowed],
        ['IR', 'Iran', CountryStatus::Blocked],
    ] as [$code, $name, $status]) {
        Country::query()->create(['code' => $code, 'name' => $name, 'status' => $status]);
    }
}
