<?php

declare(strict_types=1);

use App\Support\Money\Decimal;
use App\Support\Money\Exceptions\DivisionByZero;
use App\Support\Money\Exceptions\InvalidDecimal;

describe('construction', function (): void {
    it('accepts integers and numeric strings', function (string|int $input, string $expected): void {
        expect(Decimal::of($input)->toString())->toBe($expected);
    })->with([
        [0, '0'],
        [42, '42'],
        [-7, '-7'],
        ['1.50', '1.5'],
        ['+3.25', '3.25'],
        ['-0.000000000000000001', '-0.000000000000000001'],
        ['  12.0  ', '12'],
        ['123456789012345678.123456789012345678', '123456789012345678.123456789012345678'],
    ]);

    it('rejects anything that is not a plain decimal', function (string $input): void {
        Decimal::of($input);
    })->throws(InvalidDecimal::class)->with(['', 'abc', '1e5', '1,000.00', '1.', '.5', '0x1A', 'NaN', 'INF', '--1']);

    it('refuses floats at the type level', function (): void {
        /** @phpstan-ignore argument.type (deliberately passing a float to prove the guard) */
        Decimal::of(0.1);
    })->throws(TypeError::class);

    it('truncates input beyond 18 decimals', function (): void {
        expect(Decimal::of('0.1234567890123456789999')->toDatabase())->toBe('0.123456789012345678');
    });

    it('has a single representation of zero', function (): void {
        expect(Decimal::of('-0.000')->toDatabase())->toBe('0.000000000000000000')
            ->and(Decimal::of('0.000001')->negate()->add('0.000001')->toString())->toBe('0');
    });

    it('returns the same instance when given a Decimal', function (): void {
        $d = Decimal::of('5');

        expect(Decimal::of($d))->toBe($d);
    });
});

describe('arithmetic', function (): void {
    it('adds and subtracts exactly where floats would drift', function (): void {
        expect(Decimal::of('0.1')->add('0.2')->toString())->toBe('0.3')
            ->and(Decimal::of('1')->sub('0.9')->toString())->toBe('0.1')
            ->and(Decimal::of('100000000.000000000000000001')->sub('100000000')->toString())->toBe('0.000000000000000001');
    });

    it('multiplies at full scale', function (): void {
        expect(Decimal::of('67420.50')->mul('0.00150000')->toString())->toBe('101.13075')
            ->and(Decimal::of('-2.5')->mul('4')->toString())->toBe('-10');
    });

    it('divides with truncation toward zero', function (): void {
        expect(Decimal::of('1')->div('3')->toString())->toBe('0.333333333333333333')
            ->and(Decimal::of('-2')->div('3')->toString())->toBe('-0.666666666666666666');
    });

    it('refuses to divide by zero', function (): void {
        Decimal::of('1')->div('0.000');
    })->throws(DivisionByZero::class);

    it('computes daily staking interest like the plan specifies', function (): void {
        // principal × APR / 365, truncated to the asset's precision (USDT = 6)
        $daily = Decimal::of('2000')->mul('0.042')->div('365')->toAssetPrecision(6);

        expect($daily->toString())->toBe('0.230136');
    });

    it('is immutable', function (): void {
        $a = Decimal::of('10');
        $a->add('5');

        expect($a->toString())->toBe('10');
    });

    it('negates and takes absolute values', function (): void {
        expect(Decimal::of('3.5')->negate()->toString())->toBe('-3.5')
            ->and(Decimal::of('-3.5')->abs()->toString())->toBe('3.5')
            ->and(Decimal::of('3.5')->abs()->toString())->toBe('3.5');
    });
});

describe('rounding', function (): void {
    it('rounds down toward zero', function (string $in, int $dp, string $out): void {
        expect(Decimal::of($in)->roundDown($dp)->toString())->toBe($out);
    })->with([
        ['1.239', 2, '1.23'],
        ['-1.239', 2, '-1.23'],
        ['0.00000009', 8, '0.00000009'],
        ['0.000000099', 8, '0.00000009'],
        ['5.5', 0, '5'],
        ['1.5', -3, '1'],
        ['1.123', 99, '1.123'],
    ]);

    it('rounds half away from zero', function (string $in, int $dp, string $out): void {
        expect(Decimal::of($in)->roundHalfUp($dp)->toString())->toBe($out);
    })->with([
        ['1.235', 2, '1.24'],
        ['1.234', 2, '1.23'],
        ['-1.235', 2, '-1.24'],
        ['2.5', 0, '3'],
        ['-2.5', 0, '-3'],
        ['0.0049', 2, '0'],
    ]);

    it('formats fixed decimals without locale', function (): void {
        expect(Decimal::of('1234.5')->toFixed(2))->toBe('1234.50')
            ->and(Decimal::of('-0.129')->toFixed(2))->toBe('-0.12')
            ->and(Decimal::of('7')->toFixed(0))->toBe('7');
    });
});

describe('comparison', function (): void {
    it('compares values', function (): void {
        $a = Decimal::of('1.000000000000000001');

        expect($a->gt('1'))->toBeTrue()
            ->and($a->gte('1.000000000000000001'))->toBeTrue()
            ->and($a->lt('1'))->toBeFalse()
            ->and($a->lte('2'))->toBeTrue()
            ->and($a->eq('1.0000000000000000010'))->toBeTrue()
            ->and($a->compareTo('5'))->toBe(-1);
    });

    it('reports sign', function (): void {
        expect(Decimal::zero()->isZero())->toBeTrue()
            ->and(Decimal::of('0.000000000000000001')->isPositive())->toBeTrue()
            ->and(Decimal::of('-0.000000000000000001')->isNegative())->toBeTrue();
    });

    it('finds min and max', function (): void {
        expect(Decimal::min('3', '-1', '2')->toString())->toBe('-1')
            ->and(Decimal::max('3', Decimal::of('10.5'), 2)->toString())->toBe('10.5');
    });
});

describe('serialisation', function (): void {
    it('serialises for JSON, strings and the database', function (): void {
        $d = Decimal::of('12.340');

        expect(json_encode(['amount' => $d]))->toBe('{"amount":"12.34"}')
            ->and((string) $d)->toBe('12.34')
            ->and($d->toDatabase())->toBe('12.340000000000000000');
    });
});
