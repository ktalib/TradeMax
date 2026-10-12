<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories\Contracts;

use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * Aggregate reads over the ledger for invariant checks, snapshots, reconciliation and
 * proof of liabilities. All sums are computed by the database in DECIMAL, never in PHP floats.
 */
interface LedgerTotals
{
    /**
     * Journals whose debits and credits differ for some asset.
     *
     * @return list<array{journal_id: int, asset_id: int, debits: Decimal, credits: Decimal}>
     */
    public function unbalancedJournals(?CarbonImmutable $since = null, int $limit = 100): array;

    /**
     * Assets whose whole-ledger debits and credits differ.
     *
     * @return list<array{asset_id: int, debits: Decimal, credits: Decimal}>
     */
    public function unbalancedAssets(): array;

    /**
     * Wallets below zero whose type doesn't allow it.
     *
     * @param  list<string>  $typesAllowedNegative
     * @return list<array{wallet_id: int, type: string, available: Decimal, locked: Decimal}>
     */
    public function negativeWallets(array $typesAllowedNegative, int $limit = 100): array;

    /**
     * Wallets whose stored balances differ from the sum of their entries (a direct write).
     *
     * @return list<array{wallet_id: int, available: Decimal, locked: Decimal, entries_available: Decimal, entries_locked: Decimal}>
     */
    public function driftedWallets(int $limit = 100): array;

    /**
     * Entries whose asset differs from their wallet's asset.
     *
     * @return list<int> entry ids
     */
    public function entriesWithWrongAsset(int $limit = 100): array;

    /**
     * What the ledger says custody should hold: -(system_hot + system_cold), as of a moment.
     */
    public function custodyLiability(int $assetId, ?CarbonImmutable $asOf = null): Decimal;

    /**
     * Writes end-of-day balances for every wallet that existed by the end of $date.
     * Existing snapshots for that date are left alone.
     *
     * @return int snapshots written
     */
    public function snapshotEndOfDay(CarbonImmutable $date): int;

    /**
     * Per-user total (available + locked, all user wallet types) of one asset from the
     * snapshots of $date. Users with a zero total are left out.
     *
     * @return array<int, Decimal> keyed by user id, ascending
     */
    public function userTotalsOn(int $assetId, CarbonImmutable $date): array;
}
