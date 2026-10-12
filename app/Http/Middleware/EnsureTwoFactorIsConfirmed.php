<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-step verification is mandatory (ADR-0007): the account is unusable until an
 * authenticator app is set up and confirmed.
 */
final class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasConfirmedTwoFactor()) {
            return redirect()->guest(route('onboarding.two-factor'));
        }

        return $next($request);
    }
}
