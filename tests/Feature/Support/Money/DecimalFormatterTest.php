<?php

declare(strict_types=1);

use App\Support\Money\Decimal;
use App\Support\Money\DecimalFormatter;

it('groups and separates by locale', function (string $locale, string $expected): void {
    expect(DecimalFormatter::format(Decimal::of('1234567.891'), 2, $locale))->toBe($expected);
})->with([
    ['en', '1,234,567.89'],
    ['de', '1.234.567,89'],
    ['fr', "1\u{202F}234\u{202F}567,89"],
]);

it('never loses precision on 18-decimal values', function (): void {
    expect(DecimalFormatter::format(Decimal::of('123456789012345678.123456789012345678'), 18, 'en'))
        ->toBe('123,456,789,012,345,678.123456789012345678');
});

it('truncates instead of rounding up', function (): void {
    expect(DecimalFormatter::format(Decimal::of('0.999'), 2, 'en'))->toBe('0.99');
});

it('shows a minus sign only when the shown value is negative', function (): void {
    expect(DecimalFormatter::format(Decimal::of('-1250.5'), 2, 'en'))->toBe('-1,250.50')
        ->and(DecimalFormatter::format(Decimal::of('-0.001'), 2, 'en'))->toBe('0.00');
});

it('trims trailing zeros down to a minimum', function (): void {
    expect(DecimalFormatter::format(Decimal::of('0.42150000'), 8, 'en', trimZeros: true))->toBe('0.4215')
        ->and(DecimalFormatter::format(Decimal::of('15000'), 8, 'en', trimZeros: true))->toBe('15,000.00')
        ->and(DecimalFormatter::format(Decimal::of('15000'), 0, 'en', trimZeros: true))->toBe('15,000');
});

it('uses the application locale by default', function (): void {
    app()->setLocale('de');

    expect(DecimalFormatter::format(Decimal::of('1000'), 2))->toBe('1.000,00');
});
