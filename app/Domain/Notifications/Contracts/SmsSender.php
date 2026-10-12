<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

/**
 * Minimal SMS port used from Phase 1 (phone verification, security alerts).
 * Phase 6 replaces the drivers with Twilio/Vonage failover and per-country routing.
 */
interface SmsSender
{
    /**
     * @param  string  $e164  destination in E.164 format
     * @param  string  $message  at most 160 characters; OTP messages never contain links
     */
    public function send(string $e164, string $message): void;
}
