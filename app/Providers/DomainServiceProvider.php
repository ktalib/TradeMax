<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Binds each domain module's repository contracts (Repositories/Contracts) to their
 * Eloquent implementations. Add a module's bindings here when its repositories exist.
 */
final class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [];
}
