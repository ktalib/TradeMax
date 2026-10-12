<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the signed-in user's language; guests get their browser's preferred supported language.
 */
final class ApplyUserPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = (array) config('platform.locales', ['en']);
        $user = $request->user();

        $locale = $user instanceof User && in_array($user->locale, $supported, true)
            ? $user->locale
            : $request->getPreferredLanguage($supported);

        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
