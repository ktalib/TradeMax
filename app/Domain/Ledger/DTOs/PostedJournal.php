<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

final readonly class PostedJournal
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $idempotencyKey,
        /** True when the key had already been posted and nothing new was written. */
        public bool $replayed,
    ) {}
}
