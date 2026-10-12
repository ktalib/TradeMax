<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\ChangePassword;
use App\Domain\Identity\Actions\ConfirmEmailChange;
use App\Domain\Identity\Actions\ConfirmPhoneChange;
use App\Domain\Identity\Actions\CreateApiKey;
use App\Domain\Identity\Actions\RegenerateRecoveryCodes;
use App\Domain\Identity\Actions\RequestEmailChange;
use App\Domain\Identity\Actions\RequestPhoneChange;
use App\Domain\Identity\Actions\ResetTwoFactor;
use App\Domain\Identity\Actions\RevokeApiKey;
use App\Domain\Identity\Actions\SetAntiPhishingCode;
use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\ApiKeyPermission;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Events\SecurityEventOccurred;
use App\Domain\Identity\Models\ApiKey;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ConfirmNewEmailNotification;
use App\Domain\Identity\Notifications\OneTimeCodeNotification;
use App\Domain\Identity\Notifications\SecurityAlertNotification;
use App\Support\Audit\AuditLog;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Notification::fake();
    Event::fake([SecurityEventOccurred::class]);
    seedCountries();
});

function expectLockedAndAlerted(User $user, SecurityEvent $event): void
{
    $user->refresh();

    expect($user->withdrawalsLocked())->toBeTrue()
        ->and($user->withdrawal_locked_until->diffInHours(now(), true))->toBeGreaterThan(23.9)
        ->and(AuditLog::query()->where('action', 'security.'.$event->value)->where('subject_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, SecurityAlertNotification::class, fn (SecurityAlertNotification $n) => $n->event === $event);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e) => $e->event === $event && $e->user->is($user));
}

describe('step-up verification', function (): void {
    it('rejects a wrong authenticator code', function (): void {
        $user = User::factory()->create();
        $codes = new StepUpCodes('000000', stepUpFor($user)->email);

        expect(fn () => app(ResetTwoFactor::class)->handle($user, $codes))->toThrow(ValidationException::class);
        expect($user->refresh()->hasConfirmedTwoFactor())->toBeTrue();
    });

    it('rejects a wrong or missing email code', function (?string $emailCode): void {
        $user = User::factory()->create();

        expect(fn () => app(ResetTwoFactor::class)->handle($user, new StepUpCodes(totpFor($user), $emailCode)))->toThrow(ValidationException::class);
        expect($user->refresh()->hasConfirmedTwoFactor())->toBeTrue();
    })->with([null, '123456']);

    it('refuses to reuse an authenticator code', function (): void {
        $user = User::factory()->create();
        $codes = stepUpFor($user);
        app(RegenerateRecoveryCodes::class)->handle($user, $codes);

        expect(fn () => app(RegenerateRecoveryCodes::class)->handle($user, new StepUpCodes($codes->totp, stepUpFor($user)->email)))
            ->toThrow(ValidationException::class);
    });
});

it('changes the password, signs out other sessions and locks withdrawals', function (): void {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'current', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'other', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
    ]);

    app(ChangePassword::class)->handle($user, stepUpFor($user), UserFactory::PASSWORD, 'amber-falcon-river-stone', 'amber-falcon-river-stone', 'current');

    expect(Hash::check('amber-falcon-river-stone', $user->refresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['current']);
    expectLockedAndAlerted($user, SecurityEvent::PasswordChanged);
});

it('refuses a password change with the wrong current password', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(ChangePassword::class)->handle($user, stepUpFor($user), 'not-it', 'amber-falcon-river-stone', 'amber-falcon-river-stone', null))
        ->toThrow(ValidationException::class);
});

it('changes the email only after the new address is confirmed, and alerts both addresses', function (): void {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $change = app(RequestEmailChange::class)->handle($user, stepUpFor($user), 'New@Example.com');

    expect($user->refresh()->email)->toBe('old@example.com');
    Notification::assertSentOnDemand(ConfirmNewEmailNotification::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'new@example.com');

    app(ConfirmEmailChange::class)->handle($user, $change);

    expect($user->refresh()->email)->toBe('new@example.com');
    expectLockedAndAlerted($user, SecurityEvent::EmailChanged);
    Notification::assertSentOnDemand(SecurityAlertNotification::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => ($notifiable->routes['mail'] ?? null) === 'old@example.com');
});

it('confirms email changes through the signed link while signed in', function (): void {
    $user = signIn();
    $change = app(RequestEmailChange::class)->handle($user, stepUpFor($user), 'fresh@example.com');

    $url = URL::temporarySignedRoute('security.email.confirm', now()->addHour(), ['change' => $change->public_id]);

    $this->get($url)->assertRedirect('/security')->assertSessionHas('status');
    expect($user->refresh()->email)->toBe('fresh@example.com');

    $this->get($url)->assertRedirect('/security')->assertSessionHasErrors('email');
});

it('refuses an email that belongs to someone else', function (): void {
    $user = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    expect(fn () => app(RequestEmailChange::class)->handle($user, stepUpFor($user), 'taken@example.com'))->toThrow(ValidationException::class);
});

it('changes the phone after an SMS code to the new number, alerting the old number', function (): void {
    $user = User::factory()->create();
    $oldPhone = $user->phone;

    app(RequestPhoneChange::class)->handle($user, stepUpFor($user), '07400 123456', 'GB');

    $code = null;
    Notification::assertSentOnDemand(OneTimeCodeNotification::class, function ($n, $channels, AnonymousNotifiable $notifiable) use (&$code) {
        $code = $n->code;

        return $notifiable->routes['sms'] === '+447400123456';
    });

    expect(fn () => app(ConfirmPhoneChange::class)->handle($user, '000000'))->toThrow(ValidationException::class);
    app(ConfirmPhoneChange::class)->handle($user, (string) $code);

    expect($user->refresh()->phone)->toBe('+447400123456');
    expectLockedAndAlerted($user, SecurityEvent::PhoneChanged);
    Notification::assertSentOnDemand(SecurityAlertNotification::class, fn ($n, $channels, AnonymousNotifiable $notifiable) => ($notifiable->routes['sms'] ?? null) === $oldPhone);
});

it('resets the authenticator and sends the user back to set it up', function (): void {
    $user = User::factory()->create();

    app(ResetTwoFactor::class)->handle($user, stepUpFor($user));

    expect($user->refresh()->hasConfirmedTwoFactor())->toBeFalse();
    expectLockedAndAlerted($user, SecurityEvent::TwoFactorDisabled);

    signIn($user);
    $this->get('/dashboard')->assertRedirect('/onboarding/two-factor');
});

it('regenerates recovery codes without locking withdrawals', function (): void {
    $user = User::factory()->create();

    $codes = app(RegenerateRecoveryCodes::class)->handle($user, stepUpFor($user));

    expect($codes)->toHaveCount(8)->not->toContain('RCVR1-ALPHA')
        ->and($user->refresh()->withdrawalsLocked())->toBeFalse();
});

it('sets the anti-phishing code and shows it in security emails', function (): void {
    $user = User::factory()->create();

    app(SetAntiPhishingCode::class)->handle($user, totpFor($user), 'ORCHID-42');

    expect($user->refresh()->anti_phishing_code)->toBe('ORCHID-42');

    $mail = (new SecurityAlertNotification($user, SecurityEvent::PasswordChanged, ['time' => now()->toIso8601String()]))->toMail($user)->render();
    expect((string) $mail)->toContain('ORCHID-42')->toContain('/account/freeze/');
});

it('rejects anti-phishing codes with odd characters', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(SetAntiPhishingCode::class)->handle($user, totpFor($user), '<script>'))->toThrow(ValidationException::class);
});

describe('API keys', function (): void {
    it('issues a key whose secret is stored encrypted and returned only once', function (): void {
        $user = User::factory()->create();

        $issued = app(CreateApiKey::class)->handle($user, stepUpFor($user), 'Grid bot', ['read'], ['203.0.113.10']);

        $raw = DB::table('api_keys')->where('id', $issued->apiKey->id)->first();
        expect($issued->plainKey)->toStartWith('tmk_')
            ->and($raw->key_hash)->toBe(hash('sha256', $issued->plainKey))
            ->and($raw->secret)->not->toContain($issued->plainSecret)
            ->and(decrypt($raw->secret, false))->toBe($issued->plainSecret)
            ->and($issued->apiKey->permissions->all())->toBe([ApiKeyPermission::Read])
            ->and($issued->apiKey->ip_whitelist)->toBe(['203.0.113.10']);

        Notification::assertSentTo($user, SecurityAlertNotification::class, fn ($n) => $n->event === SecurityEvent::ApiKeyCreated);
    });

    it('requires verification level 3 for trading keys', function (): void {
        $user = User::factory()->create(['kyc_tier' => 2]);

        expect(fn () => app(CreateApiKey::class)->handle($user, stepUpFor($user), 'Bot', ['trade'], []))->toThrow(ValidationException::class);
    });

    it('validates IP addresses and permissions', function (array $permissions, array $ips): void {
        $user = User::factory()->create();

        expect(fn () => app(CreateApiKey::class)->handle($user, stepUpFor($user), 'Bot', $permissions, $ips))->toThrow(ValidationException::class);
    })->with([
        'no permissions' => [[], []],
        'unknown permission' => [['withdraw'], []],
        'bad IP' => [['read'], ['999.1.1.1']],
    ]);

    it('lets only the owner revoke a key', function (): void {
        $owner = User::factory()->create();
        $issued = app(CreateApiKey::class)->handle($owner, stepUpFor($owner), 'Bot', ['read'], []);

        expect(fn () => app(RevokeApiKey::class)->handle(User::factory()->create(), $issued->apiKey))
            ->toThrow(AuthorizationException::class);

        app(RevokeApiKey::class)->handle($owner, $issued->apiKey);
        expect(ApiKey::query()->sole()->isActive())->toBeFalse();
    });

    it('refuses new keys while the account is frozen', function (): void {
        $user = User::factory()->frozen()->create();

        expect(fn () => app(CreateApiKey::class)->handle($user, stepUpFor($user), 'Bot', ['read'], []))->toThrow(ValidationException::class);
    });
});
