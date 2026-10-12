<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\PostedByType;

final readonly class PostedBy
{
    public function __construct(
        public PostedByType $type,
        public ?int $id = null,
    ) {}

    public static function system(): self
    {
        return new self(PostedByType::System);
    }

    public static function user(int $userId): self
    {
        return new self(PostedByType::User, $userId);
    }

    public static function admin(int $adminId): self
    {
        return new self(PostedByType::Admin, $adminId);
    }
}
