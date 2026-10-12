<?php

declare(strict_types=1);

use App\Http\Controllers\Account\FreezeLinkController;
use App\Http\Controllers\Account\FrozenAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\ComponentGalleryController;
use App\Http\Controllers\Onboarding\DeviceVerificationController;
use App\Http\Controllers\Onboarding\PhoneVerificationController;
use App\Http\Controllers\Onboarding\TwoFactorSetupController;
use App\Http\Controllers\Security\EmailChangeController;
use App\Http\Controllers\Security\SecurityPageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'))->name('home');

if (app()->environment(['local', 'testing'])) {
    Route::get('/dev/ui', ComponentGalleryController::class)->name('dev.ui');
}

// New-device check: the first gate after signing in.
Route::middleware('auth')->prefix('device')->name('device.')->group(function (): void {
    Route::get('/verify', [DeviceVerificationController::class, 'show'])->name('verify');
    Route::post('/verify/send', [DeviceVerificationController::class, 'send'])->middleware('throttle:otp-send')->name('verify.send');
    Route::post('/verify', [DeviceVerificationController::class, 'confirm'])->middleware('throttle:otp-verify')->name('verify.confirm');
});

// Onboarding: email (Fortify) → phone → authenticator app.
Route::middleware(['auth', 'verified', 'device.verified'])->prefix('onboarding')->name('onboarding.')->group(function (): void {
    Route::get('/phone', [PhoneVerificationController::class, 'show'])->name('phone');
    Route::post('/phone/send', [PhoneVerificationController::class, 'send'])->middleware('throttle:otp-send')->name('phone.send');
    Route::post('/phone/verify', [PhoneVerificationController::class, 'verify'])->middleware('throttle:otp-verify')->name('phone.verify');
    Route::post('/phone/update', [PhoneVerificationController::class, 'update'])->middleware('throttle:otp-send')->name('phone.update');

    Route::middleware('phone.verified')->group(function (): void {
        Route::get('/two-factor', [TwoFactorSetupController::class, 'show'])->name('two-factor');
        Route::post('/two-factor', [TwoFactorSetupController::class, 'confirm'])->middleware('throttle:otp-verify')->name('two-factor.confirm');
        Route::get('/recovery-codes', [TwoFactorSetupController::class, 'recoveryCodes'])->name('recovery-codes');
    });
});

Route::get('/account/frozen', FrozenAccountController::class)
    ->middleware(['auth', 'verified', 'device.verified'])
    ->name('account.frozen');

// "Freeze my account" link from security emails: works without signing in, by design.
Route::middleware(['signed', 'throttle:freeze-link'])->group(function (): void {
    Route::get('/account/freeze/{user:public_id}', [FreezeLinkController::class, 'show'])->name('account.freeze');
    Route::post('/account/freeze/{user:public_id}', [FreezeLinkController::class, 'store'])->name('account.freeze.confirm');
});

Route::middleware('app')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/security', SecurityPageController::class)->name('security.index');
    Route::get('/security/email/confirm/{change:public_id}', EmailChangeController::class)
        ->middleware('signed')
        ->name('security.email.confirm');
});
