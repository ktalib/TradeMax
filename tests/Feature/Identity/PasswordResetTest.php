<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\SecurityAlertNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('sends a reset link', function (): void {
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password, ends every session and pauses withdrawals', function (): void {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'old', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->post('/forgot-password', ['email' => $user->email]);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token): bool {
        $token = $n->token;

        return true;
    });

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'cobalt-meadow-thistle-arc',
        'password_confirmation' => 'cobalt-meadow-thistle-arc',
    ])->assertRedirect('/login');

    $user->refresh();
    expect(Hash::check('cobalt-meadow-thistle-arc', $user->password))->toBeTrue()
        ->and($user->withdrawalsLocked())->toBeTrue()
        ->and(DB::table('sessions')->count())->toBe(0);

    Notification::assertSentTo($user, SecurityAlertNotification::class, fn ($n) => $n->event === SecurityEvent::PasswordReset);
});

it('applies the password rules on reset', function (): void {
    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);
    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token): bool {
        $token = $n->token;

        return true;
    });

    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertSessionHasErrors('password');
});
