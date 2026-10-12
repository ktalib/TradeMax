<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateApiKey;
use App\Domain\Identity\Actions\FreezeAccount;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\SecurityEventOccurred;
use App\Domain\Identity\Models\ApiKey;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\SecurityAlertNotification;
use App\Livewire\Security\FreezeAccountCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
    Event::fake([SecurityEventOccurred::class]);
});

it('freezes: status, API keys revoked, other sessions ended, alert and event', function (): void {
    $user = User::factory()->create();
    app(CreateApiKey::class)->handle($user, stepUpFor($user), 'Bot', ['read'], []);
    DB::table('sessions')->insert([
        ['id' => 'mine', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'attacker', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
    ]);

    app(FreezeAccount::class)->handle($user, 'security_page', 'mine');

    expect($user->refresh()->status)->toBe(UserStatus::Frozen)
        ->and($user->frozen_at)->not->toBeNull()
        ->and(ApiKey::query()->whereNull('revoked_at')->count())->toBe(0)
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['mine']);

    Notification::assertSentTo($user, SecurityAlertNotification::class, fn ($n) => $n->event === SecurityEvent::AccountFrozen && $n->context['source'] === 'security_page');
    Event::assertDispatched(SecurityEventOccurred::class, fn ($e) => $e->event === SecurityEvent::AccountFrozen);
});

it('is idempotent', function (): void {
    $user = User::factory()->frozen()->create();

    app(FreezeAccount::class)->handle($user, 'email_link');

    Notification::assertNothingSent();
});

it('freezes from the signed email link without signing in, after a confirm step', function (): void {
    $user = User::factory()->create();
    $url = URL::temporarySignedRoute('account.freeze', now()->addDays(7), ['user' => $user->public_id]);

    $this->get($url)->assertOk()->assertSee('Freeze your account?');
    expect($user->refresh()->status)->toBe(UserStatus::Active, 'opening the link alone must not freeze (link scanners)');

    $this->post($url)->assertOk()->assertSee('Your account is frozen');
    expect($user->refresh()->status)->toBe(UserStatus::Frozen);
});

it('rejects tampered or expired freeze links', function (): void {
    $user = User::factory()->create();
    $url = URL::temporarySignedRoute('account.freeze', now()->addDays(7), ['user' => $user->public_id]);

    $this->post($url.'x')->assertForbidden();

    $this->travel(8)->days();
    $this->post($url)->assertForbidden();

    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('sends frozen users to the frozen page', function (): void {
    signIn(User::factory()->frozen()->create());

    $this->get('/dashboard')->assertRedirect('/account/frozen');
    $this->get('/account/frozen')->assertOk()->assertSee('Your account is frozen');
});

it('signs out suspended users on their next request', function (): void {
    signIn(User::factory()->suspended()->create());

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('freezes from the security page', function (): void {
    $user = signIn();

    Livewire::test(FreezeAccountCard::class)
        ->set('confirming', true)
        ->call('freeze')
        ->assertRedirect('/account/frozen');

    expect($user->refresh()->isFrozen())->toBeTrue();
});
