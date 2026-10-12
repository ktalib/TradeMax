<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Drivers;

use App\Domain\Notifications\Contracts\SmsSender;

/**
 * Test driver: keeps sent messages in memory so tests can read codes and assert content.
 */
final class ArraySmsSender implements SmsSender
{
    /** @var list<array{to: string, message: string}> */
    private array $sent = [];

    public function send(string $e164, string $message): void
    {
        $this->sent[] = ['to' => $e164, 'message' => $message];
    }

    /**
     * @return list<array{to: string, message: string}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * @return list<array{to: string, message: string}>
     */
    public function sentTo(string $e164): array
    {
        return array_values(array_filter($this->sent, static fn (array $m): bool => $m['to'] === $e164));
    }
}
