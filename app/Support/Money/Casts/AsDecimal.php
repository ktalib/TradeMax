<?php

declare(strict_types=1);

namespace App\Support\Money\Casts;

use App\Support\Money\Decimal;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Reads DECIMAL(36,18) columns as Decimal and writes them back at full scale.
 * The database driver returns decimals as strings, so no float ever appears.
 *
 * @implements CastsAttributes<Decimal, Decimal|string|int>
 */
final class AsDecimal implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Decimal
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException("Column [{$key}] did not come back as a decimal string.");
        }

        return Decimal::of($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Decimal::of($value)->toDatabase();
    }
}
