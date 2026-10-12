<?php

declare(strict_types=1);

namespace App\Support\Money;

use NumberFormatter;

/**
 * Locale-aware display formatting for Decimal values without converting to float.
 *
 * Only the separator symbols come from intl; the digits are produced by bcmath, so
 * 18-decimal values never lose precision on the way to the screen.
 */
final class DecimalFormatter
{
    /** @var array<string, array{group: string, decimal: string, minus: string}> */
    private static array $symbols = [];

    /**
     * @param  int  $decimals  fixed number of decimals to show (truncated, never rounded up)
     * @param  bool  $trimZeros  drop trailing zeros beyond $minDecimals
     */
    public static function format(
        Decimal $value,
        int $decimals,
        ?string $locale = null,
        bool $trimZeros = false,
        int $minDecimals = 2,
    ): string {
        $symbols = self::symbols($locale ?? app()->getLocale());
        $fixed = $value->abs()->toFixed($decimals);

        [$integer, $fraction] = str_contains($fixed, '.') ? explode('.', $fixed, 2) : [$fixed, ''];

        if ($trimZeros && $fraction !== '') {
            $fraction = rtrim($fraction, '0');
            $fraction = str_pad($fraction, min($minDecimals, $decimals), '0');
        }

        $grouped = self::group($integer, $symbols['group']);
        $sign = $value->roundDown($decimals)->isNegative() ? $symbols['minus'] : '';

        return $sign.$grouped.($fraction === '' ? '' : $symbols['decimal'].$fraction);
    }

    private static function group(string $integer, string $separator): string
    {
        $length = strlen($integer);
        if ($length <= 3) {
            return $integer;
        }

        $head = $length % 3;
        $parts = $head > 0 ? [substr($integer, 0, $head)] : [];

        foreach (str_split(substr($integer, $head), 3) as $chunk) {
            $parts[] = $chunk;
        }

        return implode($separator, $parts);
    }

    /**
     * @return array{group: string, decimal: string, minus: string}
     */
    private static function symbols(string $locale): array
    {
        if (isset(self::$symbols[$locale])) {
            return self::$symbols[$locale];
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

        return self::$symbols[$locale] = [
            'group' => $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL) ?: ',',
            'decimal' => $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL) ?: '.',
            'minus' => $formatter->getSymbol(NumberFormatter::MINUS_SIGN_SYMBOL) ?: '-',
        ];
    }
}
