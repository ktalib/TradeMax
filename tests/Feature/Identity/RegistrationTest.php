<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Services\PhoneNumbers;
use App\Support\Audit\AuditLog;
use App\Support\Captcha\CaptchaVerifier;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedCountries();
    Notification::fake();
});

function registration(array $overrides = []): array
{
    return array_merge([
        'residence_country' => 'GB',
        'name' => 'Ada Okafor',
        'email' => 'Ada@Example.com',
        'phone' => '07911 123456',
        'password' => 'quiet-lantern-orbit-maple',
        'password_confirmation' => 'quiet-lantern-orbit-maple',
        'timezone' => 'Europe/London',
        'terms' => '1',
    ], $overrides);
}

it('shows the registration form with registrable countries only', function (): void {
    $this->get('/register')
        ->assertOk()
        ->assertSee('United Kingdom')
        ->assertSee('Nigeria')
        ->assertDontSee('Iran');
});

it('registers a user with E.164 phone, consents, a referral code and a verified first device', function (): void {
    $this->post('/register', registration())->assertRedirect();

    $user = User::query()->sole();

    expect($user->email)->toBe('ada@example.com')
        ->and($user->phone)->toBe('+447911123456')
        ->and($user->residence_country)->toBe('GB')
        ->and($user->timezone)->toBe('Europe/London')
        ->and($user->referral_code)->toMatch('/^[A-Z2-9]{8}$/')
        ->and($user->phone_verified_at)->toBeNull()
        ->and($user->kyc_tier)->toBe(0);

    expect(Consent::query()->where('user_id', $user->id)->pluck('document')->sort()->values()->all())
        ->toBe(['privacy', 'risk_disclosure', 'terms']);

    expect(UserDevice::query()->where('user_id', $user->id)->whereNotNull('verified_at')->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'user.registered')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('links the referrer when a valid referral code is given', function (): void {
    $referrer = User::factory()->create(['referral_code' => 'ABCD2345']);

    $this->post('/register', registration(['referral_code' => 'abcd2345']))->assertRedirect();

    expect(User::query()->where('email', 'ada@example.com')->value('referred_by_id'))->toBe($referrer->id);
});

it('rejects residents of blocked countries', function (): void {
    $this->post('/register', registration(['residence_country' => 'IR']))
        ->assertSessionHasErrors(['residence_country' => 'We can\'t open accounts for residents of this country.']);

    expect(User::query()->count())->toBe(0);
});

it('lets residents of restricted countries register', function (): void {
    $this->post('/register', registration(['residence_country' => 'NG', 'phone' => '0803 123 4567']))->assertSessionHasNoErrors();

    expect(User::query()->sole()->phone)->toBe('+2348031234567');
});

it('rejects invalid input with clear messages', function (array $overrides, string $field): void {
    $this->post('/register', registration($overrides))->assertSessionHasErrors($field);
})->with([
    'unknown country' => [['residence_country' => 'ZZ'], 'residence_country'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short1', 'password_confirmation' => 'short1'], 'password'],
    'weak password' => [['password' => 'password1234', 'password_confirmation' => 'password1234'], 'password'],
    'name in password' => [['password' => 'adaokafor2024', 'password_confirmation' => 'adaokafor2024'], 'password'],
    'mismatched confirmation' => [['password_confirmation' => 'something-else-entirely'], 'password'],
    'invalid phone' => [['phone' => '12345'], 'phone'],
    'landline' => [['phone' => '020 7946 0000'], 'phone'],
    'terms not accepted' => [['terms' => null], 'terms'],
    'unknown referral code' => [['referral_code' => 'NOPE2345'], 'referral_code'],
]);

it('refuses a phone number already on another account', function (): void {
    User::factory()->create(['phone' => '+447911123456']);

    $this->post('/register', registration())->assertSessionHasErrors('phone');
});

it('refuses passwords found in breaches', function (): void {
    config(['platform.security.check_breached_passwords' => true]);
    $hash = strtoupper(sha1('quiet-lantern-orbit-maple'));
    Http::fake(['api.pwnedpasswords.com/range/*' => Http::response(substr($hash, 5).':42')]);

    $this->post('/register', registration())->assertSessionHasErrors('password');
});

it('requires the captcha when Turnstile is configured', function (): void {
    config(['services.turnstile.secret' => 'secret', 'services.turnstile.site_key' => 'site']);
    app()->forgetInstance(CaptchaVerifier::class);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $this->post('/register', registration(['cf-turnstile-response' => 'bad']))->assertSessionHasErrors('captcha');
});

it('is rate limited per IP', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/register', registration(['email' => "user{$i}@example.com", 'terms' => null]));
    }

    $this->post('/register', registration())->assertStatus(429);
});
