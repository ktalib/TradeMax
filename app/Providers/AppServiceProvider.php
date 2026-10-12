<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Lazy loading, silently discarded attributes and missing attributes all throw
        // outside production, so N+1 queries and typos surface in tests.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
