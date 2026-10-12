<?php

declare(strict_types=1);

namespace App\Support\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Cloudflare Turnstile. With no secret configured (local development) it is disabled
 * and every check passes; production must set TURNSTILE_SITE_KEY and TURNSTILE_SECRET.
 */
final class TurnstileVerifier implements CaptchaVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly Http $http,
        private readonly ?string $secret,
        private readonly ?string $siteKey,
    ) {}

    public function enabled(): bool
    {
        return filled($this->secret) && filled($this->siteKey);
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = $this->http->asForm()->timeout(5)->post(self::ENDPOINT, [
                'secret' => $this->secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (ConnectionException) {
            // Fail closed: if Cloudflare can't be reached, the challenge is not passed.
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }

    public function siteKey(): ?string
    {
        return $this->siteKey;
    }
}
