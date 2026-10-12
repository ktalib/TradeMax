<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Compliance\Enums\CountryStatus;
use App\Domain\Compliance\Models\Country;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditLogger;
use App\Support\Privacy\Mask;
use App\Support\StateMachine\IllegalTransition;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

describe('user status state machine', function (): void {
    it('allows the documented transitions', function (UserStatus $from, UserStatus $to, bool $allowed): void {
        expect($from->canTransitionTo($to))->toBe($allowed);
    })->with([
        [UserStatus::Active, UserStatus::Frozen, true],
        [UserStatus::Frozen, UserStatus::Active, true],
        [UserStatus::Suspended, UserStatus::Frozen, false],
        [UserStatus::Closed, UserStatus::Active, false],
        [UserStatus::Active, UserStatus::Active, false],
    ]);

    it('throws on illegal transitions', function (): void {
        $user = User::factory()->make(['status' => UserStatus::Closed]);

        $user->transitionTo(UserStatus::Active);
    })->throws(IllegalTransition::class);

    it('only extends the withdrawal lock, never shortens it', function (): void {
        $user = User::factory()->make(['withdrawal_locked_until' => now()->addHours(48)]);

        $user->lockWithdrawalsFor(24);

        expect($user->withdrawal_locked_until->diffInHours(now(), true))->toBeGreaterThan(47);
    });
});

describe('audit log', function (): void {
    it('records actor, subject and context', function (): void {
        $user = User::factory()->create();

        $log = app(AuditLogger::class)->record('test.action', $user, ['a' => 1], ['a' => 2], $user);

        expect($log->actor_type)->toBe('user')->and($log->actor_id)->toBe($user->id)
            ->and($log->subject_id)->toBe($user->id)->and($log->after)->toBe(['a' => 2]);
    });

    it('cannot be edited or deleted', function (): void {
        $log = app(AuditLogger::class)->record('test.action');

        expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class)
            ->and(fn () => $log->delete())->toThrow(LogicException::class)
            ->and(AuditLog::query()->sole()->action)->toBe('test.action');
    });
});

it('masks personal data for display and logs', function (): void {
    expect(Mask::email('ada.okafor@example.com'))->toBe('a•••@example.com')
        ->and(Mask::phone('+447911123456'))->toBe('+447•••••••56')
        ->and(Mask::ip('203.0.113.42'))->toBe('203.•••.•••.42')
        ->and(Mask::ip('2001:db8:85a3::8a2e:370:7334'))->toBe('2001:db8:••••');
});

it('sends strict security headers with a per-request CSP nonce', function (): void {
    $response = $this->get('/login')->assertOk();

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")->toMatch("/'nonce-[A-Za-z0-9]+'/")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');

    preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $m);
    $response->assertSee('nonce="'.$m[1].'"', false);
});

it('seeds countries from ICU with sanctioned jurisdictions blocked, keeping admin edits on re-run', function (): void {
    $this->seed(CountrySeeder::class);

    expect(Country::query()->count())->toBeGreaterThan(200)
        ->and(Country::query()->find('KP')->status)->toBe(CountryStatus::Blocked)
        ->and(Country::query()->find('GB')->name)->toBe('United Kingdom');

    Country::query()->whereKey('GB')->update(['status' => CountryStatus::Restricted->value]);
    $this->seed(CountrySeeder::class);

    expect(Country::query()->find('GB')->status)->toBe(CountryStatus::Restricted);
});

it('seeds staff roles on the admin guard and creates admins from the console', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->artisan('admin:create', ['email' => 'Ops@Example.com', '--name' => 'Ops Lead', '--role' => 'support_lead'])
        ->expectsQuestion('Password (min 10 characters)', 'velvet-harbor-canyon-quill')
        ->expectsQuestion('Confirm password', 'velvet-harbor-canyon-quill')
        ->assertSuccessful();

    $admin = Admin::query()->sole();
    expect($admin->email)->toBe('ops@example.com')
        ->and($admin->hasRole('support_lead'))->toBeTrue()
        ->and($admin->can('users.freeze'))->toBeTrue()
        ->and($admin->can('roles.manage'))->toBeFalse();
});

it('refuses unknown roles and weak admin passwords', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->artisan('admin:create', ['email' => 'x@example.com', '--name' => 'X', '--role' => 'god'])->assertFailed();

    $this->artisan('admin:create', ['email' => 'x@example.com', '--name' => 'X'])
        ->expectsQuestion('Password (min 10 characters)', 'password123')
        ->expectsQuestion('Confirm password', 'password123')
        ->assertFailed();

    expect(Admin::query()->count())->toBe(0);
});
