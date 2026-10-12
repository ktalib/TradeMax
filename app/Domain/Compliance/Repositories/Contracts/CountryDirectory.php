<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories\Contracts;

use App\Domain\Compliance\Enums\CountryStatus;

/**
 * How other modules ask about countries without touching the countries table.
 */
interface CountryDirectory
{
    public function statusOf(string $code): ?CountryStatus;

    /**
     * Countries a person may register from (allowed + restricted), code => name, sorted by name.
     *
     * @return array<string, string>
     */
    public function registrable(): array;

    /**
     * @return list<string>
     */
    public function disabledFeatures(string $code): array;
}
