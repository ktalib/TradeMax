<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\ForgetDevice;
use App\Domain\Identity\Actions\SignOutSession;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDevice;
use App\Support\Audit\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function sessionRows(User $user, array $ids): void
{
    foreach ($ids as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    }
}

it('signs out one other session, never the current one', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    sessionRows($user, ['current', 'phone']);
    sessionRows($other, ['someone-else']);

    expect(app(SignOutSession::class)->handle($user, 'current', 'current'))->toBe(0)
        ->and(app(SignOutSession::class)->handle($user, 'someone-else', 'current'))->toBe(0)
        ->and(app(SignOutSession::class)->handle($user, 'phone', 'current'))->toBe(1);

    expect(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['current', 'someone-else']);
});

it('signs out all other sessions', function (): void {
    $user = User::factory()->create();
    sessionRows($user, ['current', 'a', 'b']);

    expect(app(SignOutSession::class)->handle($user, null, 'current'))->toBe(2)
        ->and(AuditLog::query()->where('action', 'user.sessions_signed_out')->exists())->toBeTrue();
});

it('forgets a device so it needs an emailed code again, keeping the history row', function (): void {
    $user = User::factory()->create();
    $device = UserDevice::query()->create([
        'user_id' => $user->id, 'fingerprint_hash' => str_repeat('c', 64), 'first_seen_at' => now(), 'last_seen_at' => now(),
        'verified_at' => now(), 'trusted_at' => now(), 'trusted_until' => now()->addDays(30),
    ]);

    app(ForgetDevice::class)->handle($user, $device);

    expect($device->refresh()->verified_at)->toBeNull()->and($device->isTrusted())->toBeFalse()
        ->and(UserDevice::query()->count())->toBe(1);

    expect(fn () => app(ForgetDevice::class)->handle(User::factory()->create(), $device))->toThrow(AuthorizationException::class);
});

it('labels devices from the user agent', function (string $ua, string $label): void {
    expect((new UserDevice(['user_agent' => $ua]))->label())->toBe($label);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36', 'Windows · Chrome'],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1', 'iPhone · Safari'],
    ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/126.0 Safari/537.36 Edg/126.0', 'Windows · Edge'],
    ['curl/8.0', 'Unknown device'],
]);
