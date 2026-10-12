<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Ledger\DTOs\InvariantViolation;
use App\Domain\Ledger\Services\LedgerInvariants;
use PHPUnit\Framework\Assert;

final class LedgerAssertions
{
    /**
     * Every journal and asset balances, no wallet breaks the sign convention, and every
     * wallet equals the sum of its entries.
     */
    public static function assertBalanced(): void
    {
        $violations = app(LedgerInvariants::class)->check();

        Assert::assertSame([], array_map(static fn (InvariantViolation $v): string => $v->message, $violations), 'Ledger invariants violated.');
    }
}
