<?php

declare(strict_types=1);

use App\Http\Middleware\ApplyUserPreferences;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureDeviceIsVerified;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/auth.php'],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Domain/Admin/Console',
        __DIR__.'/../app/Domain/Ledger/Console',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            ApplyUserPreferences::class,
        ]);

        $middleware->alias([
            'device.verified' => EnsureDeviceIsVerified::class,
            'phone.verified' => EnsurePhoneIsVerified::class,
            'two-factor.confirmed' => EnsureTwoFactorIsConfirmed::class,
            'account.active' => EnsureAccountIsActive::class,
        ]);

        // The full chain every signed-in page sits behind (AGENTS.md: auth + verified + 2FA-confirmed).
        $middleware->group('app', [
            'auth',
            'verified',
            'device.verified',
            'phone.verified',
            'two-factor.confirmed',
            'account.active',
        ]);

        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'totp_code', 'email_code', 'code']);
    })->create();
