<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Support\Money\Exceptions\DivisionByZero;
use App\Support\Money\Exceptions\InvalidDecimal;
use JsonSerializable;
use Stringable;

/**
 * Immutable arbitrary-precision decimal backed by bcmath at a fixed internal scale.
 *
 * Every amount, price, rate and fee in the platform is a Decimal. Floats are never accepted:
 * with strict_types a float argument is a TypeError, and numeric strings are validated.
 */
final class Decimal implements JsonSerializable, Stringable
{
    public const SCALE = 18;

    private const PATTERN = '/^[+-]?(\d+)(\.\d+)?$/';

    /** @var numeric-string */
    private readonly string $value;

    /**
     * @param  numeric-string  $value  already at SCALE decimals
     */
    private function __construct(string $value)
    {
        // bcmath can yield "-0.000…"; normalise so zero has one representation.
        $this->value = bccomp($value, '0', self::SCALE) === 0 ? bcadd('0', '0', self::SCALE) : $value;
    }

    public static function of(self|string|int $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $string = trim((string) $value);

        // is_numeric alone would accept "1e5" and " .5"; the pattern narrows it to plain decimals.
        if (preg_match(self::PATTERN, $string) !== 1 || ! is_numeric($string)) {
            throw InvalidDecimal::fromInput($string);
        }

        return new self(bcadd($string, '0', self::SCALE));
    }

    public static function zero(): self
    {
        return new self(bcadd('0', '0', self::SCALE));
    }

    public static function min(self|string|int $first, self|string|int ...$rest): self
    {
        $min = self::of($first);

        foreach ($rest as $candidate) {
            $candidate = self::of($candidate);
            if ($candidate->lt($min)) {
                $min = $candidate;
            }
        }

        return $min;
    }

    public static function max(self|string|int $first, self|string|int ...$rest): self
    {
        $max = self::of($first);

        foreach ($rest as $candidate) {
            $candidate = self::of($candidate);
            if ($candidate->gt($max)) {
                $max = $candidate;
            }
        }

        return $max;
    }

    public function add(self|string|int $other): self
    {
        return new self(bcadd($this->value, self::of($other)->value, self::SCALE));
    }

    public function sub(self|string|int $other): self
    {
        return new self(bcsub($this->value, self::of($other)->value, self::SCALE));
    }

    public function mul(self|string|int $other): self
    {
        return new self(bcmul($this->value, self::of($other)->value, self::SCALE));
    }

    /**
     * Division truncates toward zero at SCALE decimals.
     */
    public function div(self|string|int $divisor): self
    {
        $divisor = self::of($divisor);

        if ($divisor->isZero()) {
            throw DivisionByZero::create();
        }

        return new self(bcdiv($this->value, $divisor->value, self::SCALE));
    }

    public function negate(): self
    {
        return new self(bcmul($this->value, '-1', self::SCALE));
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    /**
     * Truncate toward zero to the given number of decimals. Use this for balances and
     * payouts: the platform never credits a fraction it does not hold.
     */
    public function roundDown(int $decimals): self
    {
        return new self(bcadd(bcadd($this->value, '0', $this->clampScale($decimals)), '0', self::SCALE));
    }

    /**
     * Round half away from zero to the given number of decimals.
     */
    public function roundHalfUp(int $decimals): self
    {
        $decimals = $this->clampScale($decimals);
        $half = bcdiv('5', bcpow('10', (string) ($decimals + 1)), self::SCALE);
        $shifted = $this->isNegative()
            ? bcsub($this->value, $half, self::SCALE)
            : bcadd($this->value, $half, self::SCALE);

        return new self(bcadd(bcadd($shifted, '0', $decimals), '0', self::SCALE));
    }

    /**
     * Truncate to an asset's precision (e.g. 8 for BTC, 6 for USDT).
     */
    public function toAssetPrecision(int $precision): self
    {
        return $this->roundDown($precision);
    }

    public function compareTo(self|string|int $other): int
    {
        return bccomp($this->value, self::of($other)->value, self::SCALE);
    }

    public function eq(self|string|int $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function gt(self|string|int $other): bool
    {
        return $this->compareTo($other) === 1;
    }

    public function gte(self|string|int $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function lt(self|string|int $other): bool
    {
        return $this->compareTo($other) === -1;
    }

    public function lte(self|string|int $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', self::SCALE) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', self::SCALE) === 1;
    }

    public function isNegative(): bool
    {
        return bccomp($this->value, '0', self::SCALE) === -1;
    }

    /**
     * Plain string with exactly $decimals decimals, truncated. No grouping or locale:
     * use DecimalFormatter for anything shown to a user.
     *
     * @return numeric-string
     */
    public function toFixed(int $decimals): string
    {
        return bcadd($this->value, '0', $this->clampScale($decimals));
    }

    /**
     * Canonical string without trailing zeros, e.g. "1.5", "-0.000001", "42".
     *
     * @return numeric-string
     */
    public function toString(): string
    {
        $trimmed = rtrim(rtrim($this->value, '0'), '.');

        return is_numeric($trimmed) ? $trimmed : '0';
    }

    /**
     * Full-scale string, suitable for DECIMAL(36,18) columns.
     *
     * @return numeric-string
     */
    public function toDatabase(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private function clampScale(int $decimals): int
    {
        return max(0, min($decimals, self::SCALE));
    }
}
