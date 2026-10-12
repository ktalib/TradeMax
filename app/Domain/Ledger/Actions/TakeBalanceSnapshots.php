<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Repositories\Contracts\LedgerTotals;
use Carbon\CarbonImmutable;

/**
 * End-of-day balances for every wallet. Safe to re-run: existing rows for the day stay.
 */
final class TakeBalanceSnapshots
{
    public function __construct(private readonly LedgerTotals $totals) {}

    /**
     * @param  CarbonImmutable|null  $date  the UTC day to close; defaults to yesterday
     * @return int snapshots written
     */
    public function handle(?CarbonImmutable $date = null): int
    {
        $date ??= CarbonImmutable::now('UTC')->subDay();

        return $this->totals->snapshotEndOfDay($date);
    }
}
