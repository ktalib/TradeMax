<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\DTOs\InvariantViolation;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Repositories\Contracts\LedgerTotals;
use Carbon\CarbonImmutable;

/**
 * The rules the ledger must always satisfy (PLAN §3.2). Used by `ledger:invariant-check`
 * and by LedgerAssertions in tests.
 */
final class LedgerInvariants
{
    public function __construct(private readonly LedgerTotals $totals) {}

    /**
     * @param  CarbonImmutable|null  $journalsSince  limit the per-journal check to recent journals
     * @return list<InvariantViolation>
     */
    public function check(?CarbonImmutable $journalsSince = null): array
    {
        $violations = [];

        foreach ($this->totals->unbalancedJournals($journalsSince) as $row) {
            $violations[] = new InvariantViolation(
                InvariantViolation::UNBALANCED_JOURNAL,
                "Journal {$row['journal_id']} asset {$row['asset_id']}: debits {$row['debits']} != credits {$row['credits']}",
            );
        }

        foreach ($this->totals->unbalancedAssets() as $row) {
            $violations[] = new InvariantViolation(
                InvariantViolation::UNBALANCED_ASSET,
                "Asset {$row['asset_id']}: total debits {$row['debits']} != total credits {$row['credits']}",
            );
        }

        $mayGoNegative = array_values(array_map(
            static fn (WalletType $t): string => $t->value,
            array_filter(WalletType::cases(), static fn (WalletType $t): bool => $t->allowsNegative()),
        ));

        foreach ($this->totals->negativeWallets($mayGoNegative) as $row) {
            $violations[] = new InvariantViolation(
                InvariantViolation::NEGATIVE_BALANCE,
                "Wallet {$row['wallet_id']} ({$row['type']}) is negative: available {$row['available']}, locked {$row['locked']}",
            );
        }

        foreach ($this->totals->driftedWallets() as $row) {
            $violations[] = new InvariantViolation(
                InvariantViolation::BALANCE_DRIFT,
                "Wallet {$row['wallet_id']} holds {$row['available']}/{$row['locked']} but its entries sum to {$row['entries_available']}/{$row['entries_locked']}",
            );
        }

        foreach ($this->totals->entriesWithWrongAsset() as $entryId) {
            $violations[] = new InvariantViolation(
                InvariantViolation::ASSET_MISMATCH,
                "Journal entry {$entryId} has a different asset from its wallet",
            );
        }

        return $violations;
    }
}
