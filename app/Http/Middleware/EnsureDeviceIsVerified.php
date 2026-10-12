<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Services\DeviceRecognizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A session that signed in from a new device (or an untrusted device on a new IP) must
 * pass the emailed code before it can use the account.
 */
final class EnsureDeviceIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->session()->has(DeviceRecognizer::SESSION_KEY)) {
            return $request->expectsJson()
                ? response()->json(['error' => ['code' => 'device_verification_required', 'message' => __('Confirm this device first.')]], 403)
                : redirect()->guest(route('device.verify'));
        }

        return $next($request);
    }
}
