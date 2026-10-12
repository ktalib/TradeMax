<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Compliance\Repositories\Contracts\CountryDirectory;
use App\Domain\Identity\Actions\AuthenticateUser;
use App\Domain\Identity\Actions\RegisterUser;
use App\Domain\Identity\Actions\ResetUserPassword;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PhoneNumbers;
use App\Support\Captcha\CaptchaVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Routes are declared in routes/auth.php so Fortify's 2FA management endpoints stay off.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(RegisterUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(fn (Request $request): ?User => $this->app->make(AuthenticateUser::class)($request));

        Fortify::loginView(fn (Request $request) => view('auth.login', [
            'showCaptcha' => AuthenticateUser::captchaRequired($request),
            'captchaSiteKey' => $this->app->make(CaptchaVerifier::class)->siteKey(),
        ]));

        Fortify::registerView(function () {
            $countries = $this->app->make(CountryDirectory::class)->registrable();
            $phones = $this->app->make(PhoneNumbers::class);

            $callingCodes = [];
            foreach (array_keys($countries) as $code) {
                $callingCodes[$code] = $phones->callingCode($code);
            }

            return view('auth.register', [
                'countries' => $countries,
                'callingCodes' => $callingCodes,
                'captchaSiteKey' => $this->app->make(CaptchaVerifier::class)->siteKey(),
            ]);
        });

        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['token' => $request->route('token'), 'email' => $request->string('email')->toString()]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));

            // 5 a minute per email+IP, and 20 an hour per email across all IPs (slows spraying).
            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perHour(20)->by('email:'.$email),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id', $request->ip())));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by((string) $request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by((string) $request->ip()));
        RateLimiter::for('otp-send', fn (Request $request) => Limit::perMinutes(10, 6)->by('otp-send:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, 10)->by('otp-verify:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('freeze-link', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
    }
}
