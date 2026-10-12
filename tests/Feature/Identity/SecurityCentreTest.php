<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateApiKey;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\ApiKey;
use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Notifications\OneTimeCodeNotification;
use App\Livewire\Security\AntiPhishingSettings;
use App\Livewire\Security\ApiKeys;
use App\Livewire\Security\ContactSettings;
use App\Livewire\Security\DevicesAndSessions;
use App\Livewire\Security\PasswordSettings;
use App\Livewire\Security\TwoFactorSettings;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
    seedCountries();
});

it('renders every security panel', function (): void {
    signIn();

    $this->get('/security')->assertOk()
        ->assertSeeLivewire(TwoFactorSettings::class)
        ->assertSeeLivewire(PasswordSettings::class)
        ->assertSeeLivewire(ContactSettings::class)
        ->assertSeeLivewire(AntiPhishingSettings::class)
        ->assertSeeLivewire(ApiKeys::class)
        ->assertSeeLivewire(DevicesAndSessions::class);
});

it('emails a step-up code on request', function (): void {
    $user = signIn();

    Livewire::test(PasswordSettings::class)->call('open')->call('sendStepUpCode')->assertSet('emailCodeSent', true);

    Notification::assertSentTo($user, OneTimeCodeNotification::class, fn ($n) => $n->purpose === OtpPurpose::StepUp);
});

it('changes the password through the panel with both codes', function (): void {
    $user = signIn();
    $codes = stepUpFor($user);

    Livewire::test(PasswordSettings::class)
        ->call('open')
        ->set('current_password', UserFactory::PASSWORD)
        ->set('password', 'amber-falcon-river-stone')
        ->set('password_confirmation', 'amber-falcon-river-stone')
        ->set('totpCode', (string) $codes->totp)
        ->set('emailCode', (string) $codes->email)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertDispatched('toast');

    expect(Hash::check('amber-falcon-river-stone', $user->refresh()->password))->toBeTrue();
});

it('shows a field error when the step-up codes are missing', function (): void {
    signIn();

    Livewire::test(PasswordSettings::class)
        ->call('open')
        ->set('current_password', UserFactory::PASSWORD)
        ->set('password', 'amber-falcon-river-stone')
        ->set('password_confirmation', 'amber-falcon-river-stone')
        ->call('save')
        ->assertHasErrors('totp_code');
});

it('shows a new API secret once, outside the component state', function (): void {
    $user = signIn();
    $codes = stepUpFor($user);

    $component = Livewire::test(ApiKeys::class)
        ->call('open')
        ->set('name', 'Grid bot')
        ->set('totpCode', (string) $codes->totp)
        ->set('emailCode', (string) $codes->email)
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('The secret is never shown again');

    expect($component->snapshot['data'])->not->toHaveKey('issued')
        ->and(ApiKey::query()->where('user_id', $user->id)->count())->toBe(1);

    $component->call('open')->assertDontSee('The secret is never shown again');
});

it('revokes an API key from the list', function (): void {
    $user = signIn();
    $issued = app(CreateApiKey::class)->handle($user, stepUpFor($user), 'Bot', ['read'], []);

    Livewire::test(ApiKeys::class)->call('revoke', $issued->apiKey->public_id)->assertDispatched('toast');

    expect($issued->apiKey->refresh()->isActive())->toBeFalse();
});

it('saves the anti-phishing code with an authenticator code', function (): void {
    $user = signIn();

    Livewire::test(AntiPhishingSettings::class)
        ->call('open')
        ->set('anti_phishing_code', 'ORCHID-42')
        ->set('totpCode', totpFor($user))
        ->call('save')
        ->assertHasNoErrors();

    expect($user->refresh()->anti_phishing_code)->toBe('ORCHID-42');
});

it('regenerates recovery codes and shows them once', function (): void {
    $user = signIn();
    $codes = stepUpFor($user);

    Livewire::test(TwoFactorSettings::class)
        ->call('start', 'recovery')
        ->set('totpCode', (string) $codes->totp)
        ->set('emailCode', (string) $codes->email)
        ->call('regenerate')
        ->assertHasNoErrors()
        ->assertSee('Your new recovery codes');
});

it('starts a phone change and asks for the code sent to the new number', function (): void {
    $user = signIn();
    $codes = stepUpFor($user);

    Livewire::test(ContactSettings::class)
        ->call('edit', 'phone')
        ->set('phone_country', 'GB')
        ->set('phone', '07400 123456')
        ->set('totpCode', (string) $codes->totp)
        ->set('emailCode', (string) $codes->email)
        ->call('requestPhone')
        ->assertHasNoErrors()
        ->assertSet('editing', 'phone-code');
});

it('lists sessions, devices and recent sign-ins', function (): void {
    $user = signIn();
    LoginAttempt::query()->create(['user_id' => $user->id, 'email' => $user->email, 'ip' => '203.0.113.7', 'success' => false, 'reason' => 'two_factor_failed']);

    Livewire::test(DevicesAndSessions::class)->assertOk()->assertSee('Wrong 2FA code')->assertSee('203.•••.•••.7');
});
