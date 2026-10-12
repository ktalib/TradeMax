<?php

declare(strict_types=1);

use App\Domain\Assets\DTOs\AssetData;
use App\Domain\Assets\Models\Asset;
use App\Domain\Compliance\Enums\CountryStatus;
use App\Domain\Compliance\Models\Country;
use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DeviceRecognizer;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\JournalDTO;
use App\Domain\Ledger\DTOs\PostedBy;
use App\Domain\Ledger\DTOs\PostedJournal;
use App\Domain\Ledger\DTOs\WalletBalance;
use App\Domain\Ledger\DTOs\WalletRef;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Repositories\Contracts\Wallets;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\Drivers\ArraySmsSender;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
| Feature tests boot the framework on a fresh database; Unit tests stay framework-free.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Concurrency tests run real parallel processes, which can't see data inside an open test
// transaction, so they commit their fixtures and the tables are truncated between tests.
pest()->extend(TestCase::class)->use(DatabaseTruncation::class)->in('Concurrency');

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

function createAsset(string $symbol = 'BTC', int $precision = 8, string $tolerance = '0'): AssetData
{
    $asset = Asset::query()->create([
        'symbol' => $symbol,
        'name' => $symbol,
        'precision' => $precision,
        'display_precision' => min($precision, 8),
        'reconcile_tolerance' => $tolerance,
    ]);

    return AssetData::fromModel($asset->refresh());
}

function userWallet(int $userId, AssetData $asset, WalletType $type = WalletType::Spot): WalletRef
{
    return app(Wallets::class)->forUser($userId, $asset->id, $type);
}

function systemWallet(AssetData $asset, WalletType $type = WalletType::SystemHot): WalletRef
{
    return app(Wallets::class)->system($asset->id, $type);
}

function balanceOf(WalletRef $wallet): WalletBalance
{
    return app(Wallets::class)->balance($wallet->id);
}

/**
 * @param  list<EntryDTO>  $entries
 */
function postJournal(array $entries, ?string $key = null, string $reference = 'test'): PostedJournal
{
    return app(LedgerService::class)->post(new JournalDTO(
        referenceType: 'test',
        referenceId: $reference,
        idempotencyKey: $key ?? 'test:'.Str::ulid(),
        description: 'Test journal',
        entries: $entries,
        postedBy: PostedBy::system(),
    ));
}

/**
 * An on-chain deposit as Phase 4 will post it: custody (system_hot) to the user's spot wallet.
 */
function deposit(int $userId, AssetData $asset, string $amount, ?string $key = null): PostedJournal
{
    return postJournal([
        EntryDTO::debit(systemWallet($asset), $amount),
        EntryDTO::credit(userWallet($userId, $asset), $amount),
    ], $key, 'deposit');
}
