<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Assets\Repositories\EloquentAssetCatalog;
use App\Domain\Compliance\Repositories\Contracts\CountryDirectory;
use App\Domain\Compliance\Repositories\EloquentCountryDirectory;
use App\Domain\Identity\Listeners\RecordFailedTwoFactor;
use App\Domain\Identity\Listeners\RecordSuccessfulLogin;
use App\Domain\Identity\Repositories\Contracts\SessionStore;
use App\Domain\Identity\Repositories\DatabaseSessionStore;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Ledger\Repositories\Contracts\LedgerTotals;
use App\Domain\Ledger\Repositories\Contracts\Wallets;
use App\Domain\Ledger\Repositories\DatabaseLedgerTotals;
use App\Domain\Ledger\Repositories\EloquentWallets;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\Drivers\ArraySmsSender;
use App\Domain\Notifications\Drivers\LogSmsSender;
use App\Support\Captcha\CaptchaVerifier;
use App\Support\Captcha\TurnstileVerifier;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

/**
 * Binds each domain module's contracts to their implementations and wires module listeners.
 */
final class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CountryDirectory::class => EloquentCountryDirectory::class,
        SessionStore::class => DatabaseSessionStore::class,
        AssetCatalog::class => EloquentAssetCatalog::class,
        Wallets::class => EloquentWallets::class,
        LedgerTotals::class => DatabaseLedgerTotals::class,
    ];

    public function register(): void
    {
        $this->app->singleton(PhoneNumberUtil::class, fn (): PhoneNumberUtil => PhoneNumberUtil::getInstance());

        $this->app->singleton(OtpService::class, fn (Application $app): OtpService => new OtpService(
            $app->make(RateLimiter::class),
            (string) config('app.key'),
        ));

        $this->app->singleton(SmsSender::class, fn (Application $app): SmsSender => match (config('sms.driver')) {
            'fake' => new ArraySmsSender,
            default => new LogSmsSender($app->make(LoggerInterface::class)),
        });

        $this->app->singleton(CaptchaVerifier::class, fn (Application $app): CaptchaVerifier => new TurnstileVerifier(
            $app->make(Http::class),
            config('services.turnstile.secret'),
            config('services.turnstile.site_key'),
        ));
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordSuccessfulLogin::class);
        Event::listen(TwoFactorAuthenticationFailed::class, RecordFailedTwoFactor::class);
    }
}
