<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status === UserStatus::Active) {
            return $next($request);
        }

        if ($user->status === UserStatus::Frozen) {
            return redirect()->route('account.frozen');
        }

        // Suspended or closed while signed in: end the session.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => __('This account can\'t sign in. Contact support for help.')]);
    }
}
