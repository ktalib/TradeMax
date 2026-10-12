<?php

declare(strict_types=1);

namespace App\Domain\Assets\Exceptions;

use RuntimeException;

final class UnknownAsset extends RuntimeException
{
    public static function id(int $id): self
    {
        return new self("No asset with id {$id}.");
    }

    public static function symbol(string $symbol): self
    {
        return new self("No asset with symbol {$symbol}.");
    }
}
