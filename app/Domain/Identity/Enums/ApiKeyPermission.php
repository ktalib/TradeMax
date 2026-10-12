<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * Withdrawals are deliberately absent: they can never be created with an API key.
 */
enum ApiKeyPermission: string
{
    case Read = 'read';
    case Trade = 'trade';

    public function label(): string
    {
        return match ($this) {
            self::Read => __('Read balances, orders and history'),
            self::Trade => __('Place and cancel orders'),
        };
    }
}
