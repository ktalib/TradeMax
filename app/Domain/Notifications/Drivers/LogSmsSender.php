<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Drivers;

use App\Domain\Notifications\Contracts\SmsSender;
use App\Support\Privacy\Mask;
use Psr\Log\LoggerInterface;

/**
 * Local development: writes the message to the log so codes can be read without an SMS
 * provider. The phone number is masked; never use this driver in production.
 */
final class LogSmsSender implements SmsSender
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(string $e164, string $message): void
    {
        $this->logger->info('SMS to '.Mask::phone($e164).': '.$message);
    }
}
