<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Models\ApiKey;

/**
 * Returned once, right after creation. The plain key and secret are never retrievable again.
 */
final readonly class IssuedApiKey
{
    public function __construct(
        public ApiKey $apiKey,
        public string $plainKey,
        public string $plainSecret,
    ) {}
}
