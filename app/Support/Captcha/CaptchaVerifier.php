<?php

declare(strict_types=1);

namespace App\Support\Captcha;

interface CaptchaVerifier
{
    public function enabled(): bool;

    public function verify(?string $token, ?string $ip): bool;

    public function siteKey(): ?string;
}
