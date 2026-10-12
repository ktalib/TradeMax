<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\OneTimeCodeNotification;
use App\Domain\Identity\Notifications\SecurityAlertNotification;
use App\Domain\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

beforeEach(fn () => Notification::fake());

it('walks the gates in order: email, device, phone, authenticator, then the app', function (): void {
    signIn(User::factory()->unverified()->withoutVerifiedPhone()->withoutTwoFactor()->create());
    $this->get('/dashboard')->assertRedirect('/email/verify');

    signIn(User::factory()->withoutVerifiedPhone()->withoutTwoFactor()->create());
    $this->get('/dashboard')->assertRedirect('/onboarding/phone');

    signIn(User::factory()->withoutTwoFactor()->create());
    $this->get('/dashboard')->assertRedirect('/onboarding/two-factor');

    signIn();
    $this->get('/dashboard')->assertOk()->assertSee('Account checklist');
});

it('protects every app page with the full middleware chain', function (string $uri): void {
    $this->get($uri)->assertRedirect('/login');
})->with(['/dashboard', '/security']);

it('verifies the phone with an SMS code', function (): void {
    $user = signIn(User::factory()->withoutVerifiedPhone()->withoutTwoFactor()->create());

    $this->get('/onboarding/phone')->assertOk();
    $this->post('/onboarding/phone/send')->assertSessionHas('status');

    $code = null;
    Notification::assertSentTo($user, OneTimeCodeNotification::class, function (OneTimeCodeNotification $n) use (&$code, $user): bool {
        $code = $n->code;
        $text = $n->toSms($user);

        return $n->via($user) === [SmsChannel::class]
            && mb_strlen($text) <= 160
            && ! str_contains($text, 'http')
            && str_contains($text, $n->code);
    });

    $this->post('/onboarding/phone/verify', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/onboarding/phone/verify', ['code' => $code])->assertRedirect('/onboarding/two-factor');

    expect($user->refresh()->hasVerifiedPhone())->toBeTrue();
});

it('delivers SMS through the configured sender', function (): void {
    // Undo beforeEach's fake: a real manager routes "sms" through SmsChannel to the array sender.
    Notification::swap(new ChannelManager(app()));
    $user = signIn(User::factory()->withoutVerifiedPhone()->withoutTwoFactor()->create());

    $this->post('/onboarding/phone/send');

    expect(sms()->sentTo($user->phone))->toHaveCount(1);
});

it('lets the user fix an unverified phone number', function (): void {
    $user = signIn(User::factory()->withoutVerifiedPhone()->withoutTwoFactor()->create());

    $this->post('/onboarding/phone/update', ['phone' => '07400 123456', 'phone_country' => 'GB'])->assertSessionHasNoErrors();

    expect($user->refresh()->phone)->toBe('+447400123456');
});

it('sets up the authenticator app and shows recovery codes once', function (): void {
    $user = signIn(User::factory()->withoutTwoFactor()->create());

    $this->get('/onboarding/two-factor')->assertOk()->assertSee('<svg', false);

    $secret = decrypt((string) $user->refresh()->two_factor_secret);
    $this->get('/onboarding/two-factor');
    expect(decrypt((string) $user->refresh()->two_factor_secret))->toBe($secret, 'reloading must keep the same secret');

    $this->post('/onboarding/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/onboarding/two-factor', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/onboarding/recovery-codes');

    $this->get('/onboarding/recovery-codes')->assertOk()->assertSee('Two-step verification is on');
    $this->get('/onboarding/recovery-codes')->assertRedirect('/dashboard');

    expect($user->refresh()->hasConfirmedTwoFactor())->toBeTrue();
    Notification::assertSentTo($user, SecurityAlertNotification::class, fn ($n) => $n->event === SecurityEvent::TwoFactorEnabled);
});
