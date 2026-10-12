<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Enums\CountryStatus;
use App\Domain\Compliance\Models\Country;
use App\Domain\Compliance\Repositories\Contracts\CountryDirectory;

final class EloquentCountryDirectory implements CountryDirectory
{
    public function statusOf(string $code): ?CountryStatus
    {
        return Country::query()->find(strtoupper($code))?->status;
    }

    public function registrable(): array
    {
        /** @var array<string, string> */
        return Country::query()
            ->where('status', '!=', CountryStatus::Blocked->value)
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();
    }

    public function disabledFeatures(string $code): array
    {
        return Country::query()->find(strtoupper($code))->features_disabled ?? [];
    }
}
