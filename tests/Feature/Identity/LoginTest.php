<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Notifications\OneTimeCodeNotification;
use App\Domain\Identity\Notifications\SecurityAlertNotification;
use App\Domain\Identity\Services\DeviceRecognizer;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function loginAs(User $user, string $password = UserFactory::PASSWORD): \Illuminate\Testing\TestResponse
{
    return test()->post('/login', ['email' => $user->email, 'password' => $password]);
}

it('sends users with two-step verification to the challenge, then signs them in', function (): void {
    $user = User::factory()->create();

    loginAs($user)->assertRedirect('/two-factor-challenge');
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => totpFor($user)])->assertRedirect();
    $this->assertAuthenticatedAs($user);

    expect(LoginAttempt::query()->where('user_id', $user->id)->where('success', true)->count())->toBe(1)
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('accepts a recovery code at the challenge, once', function (): void {
    $user = User::factory()->create();

    loginAs($user);
    $this->post('/two-factor-challenge', ['recovery_code' => 'RCVR1-ALPHA'])->assertRedirect();
    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->recoveryCodes())->not->toContain('RCVR1-ALPHA');
});

it('records a wrong 2FA code as a failed attempt', function (): void {
    $user = User::factory()->create();

    loginAs($user);
    $this->post('/two-factor-challenge', ['code' => '000000']);

    $this->assertGuest();
    expect(LoginAttempt::query()->where('reason', 'two_factor_failed')->count())->toBe(1);
});

it('records failed attempts with a reason, without revealing whether the email exists', function (): void {
    $user = User::factory()->create();

    loginAs($user, 'wrong-password-123')->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever-123'])->assertSessionHasErrors('email');

    expect(LoginAttempt::query()->where('success', false)->where('reason', 'invalid_credentials')->count())->toBe(2)
        ->and(session('errors')->first('email'))->toBe(__('auth.failed'));
});

it('refuses suspended accounts', function (): void {
    $user = User::factory()->suspended()->create();

    loginAs($user)->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(LoginAttempt::query()->where('reason', 'account_suspended')->exists())->toBeTrue();
});

it('throttles after 5 attempts a minute for the same email and IP', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        loginAs($user, 'wrong-password-'.$i);
    }

    loginAs($user)->assertStatus(429);
});

it('asks for a CAPTCHA after 3 failures from one IP when Turnstile is configured', function (): void {
    config(['services.turnstile.secret' => 'secret', 'services.turnstile.site_key' => 'site']);
    app()->forgetInstance(\App\Support\Captcha\CaptchaVerifier::class);
    $user = User::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        loginAs($user, 'wrong-password-'.$i);
    }

    $this->get('/login')->assertSee('cf-turnstile');
    loginAs($user)->assertSessionHasErrors('captcha');
});

it('requires an emailed code on a new device and alerts the user', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    loginAs($user);
    $this->assertAuthenticatedAs($user);

    $this->get('/dashboard')->assertRedirect('/device/verify');
    Notification::assertSentTo($user, SecurityAlertNotification::class, fn ($n) => $n->event === SecurityEvent::NewDeviceLogin);

    $this->post('/device/verify/send')->assertSessionHas('status');

    $code = null;
    Notification::assertSentTo($user, OneTimeCodeNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $this->post('/device/verify', ['code' => '999999'])->assertSessionHasErrors('code');
    $this->post('/device/verify', ['code' => $code, 'remember' => '1'])->assertRedirect();

    $device = UserDevice::query()->where('user_id', $user->id)->sole();
    expect($device->verified_at)->not->toBeNull()
        ->and($device->isTrusted())->toBeTrue()
        ->and(session(DeviceRecognizer::SESSION_KEY))->toBe($device->id);
});

it('lets a trusted device sign in without the emailed code', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();
    $token = str_repeat('a', 40);

    UserDevice::query()->create([
        'user_id' => $user->id,
        'fingerprint_hash' => hash('sha256', $token),
        'ip' => '127.0.0.1',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'verified_at' => now(),
        'trusted_until' => now()->addDays(10),
    ]);

    $this->withCookie(DeviceRecognizer::COOKIE, $token)->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

    expect(session(DeviceRecognizer::SESSION_KEY))->not->toBeNull();
    Notification::assertNotSentTo($user, SecurityAlertNotification::class);
});

it('asks again when a verified but untrusted device appears from a new IP', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();
    $token = str_repeat('b', 40);

    UserDevice::query()->create([
        'user_id' => $user->id,
        'fingerprint_hash' => hash('sha256', $token),
        'ip' => '203.0.113.9',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'verified_at' => now(),
    ]);

    $this->withCookie(DeviceRecognizer::COOKIE, $token)->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

    expect(session(DeviceRecognizer::SESSION_KEY))->toBeNull();
    Notification::assertSentTo($user, SecurityAlertNotification::class);
});

it('signs out', function (): void {
    signIn();

    $this->post('/logout')->assertRedirect();
    $this->assertGuest();
});
