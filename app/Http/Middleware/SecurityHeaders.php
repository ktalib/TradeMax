<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP with a per-request nonce for our inline scripts. 'unsafe-eval' is required because
 * Alpine (bundled with Livewire 3) evaluates expressions at runtime, and inline style
 * attributes need 'unsafe-inline' for styles only (ADR-0007). Everything else is locked down.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $scriptSrc = ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'", 'https://challenges.cloudflare.com'];
        $connectSrc = ["'self'"];
        $styleSrc = ["'self'", "'unsafe-inline'"];

        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $scriptSrc[] = $hot;
            $styleSrc[] = $hot;
            $connectSrc[] = $hot;
            $connectSrc[] = (string) preg_replace('/^http/', 'ws', $hot);
        }

        $csp = implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            "img-src 'self' data:",
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connectSrc),
            'frame-src https://challenges.cloudflare.com',
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
