<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * Sign convention (ADR-0003): a credit increases a wallet, a debit decreases it. User
 * wallets never go below zero. Counter-accounts mirror custody and may go negative.
 */
enum WalletType: string
{
    case Spot = 'spot';
    case Staking = 'staking';
    case Copy = 'copy';

    /** Trading, withdrawal and other fees earned. Paid out only by jobs that lock it. */
    case SystemFee = 'system_fee';

    /** Funds staking interest. Never minted: an empty pool pauses accruals. */
    case SystemYieldPool = 'system_yield_pool';

    /** Counter-account for hot custody: goes negative as deposits arrive. */
    case SystemHot = 'system_hot';

    /** Counter-account for cold custody. */
    case SystemCold = 'system_cold';

    /** Money that can't be attributed yet (e.g. unmatched deposits). */
    case SystemSuspense = 'system_suspense';

    /** Demo environment only: funds demo users without real custody. */
    case SystemDemoFaucet = 'system_demo_faucet';

    public function isSystem(): bool
    {
        return ! in_array($this, self::userTypes(), true);
    }

    public function allowsNegative(): bool
    {
        return match ($this) {
            self::SystemHot, self::SystemCold, self::SystemDemoFaucet => true,
            default => false,
        };
    }

    /**
     * Written by most money flows, so LedgerService doesn't hold a row lock on them for
     * the whole transaction (ADR-0003). Debiting system_fee needs an explicit lock.
     */
    public function isHotRow(): bool
    {
        return $this === self::SystemFee || $this === self::SystemHot;
    }

    /**
     * @return list<self>
     */
    public static function userTypes(): array
    {
        return [self::Spot, self::Staking, self::Copy];
    }

    /**
     * @return list<self>
     */
    public static function systemTypes(bool $includeDemo = false): array
    {
        $types = [self::SystemFee, self::SystemYieldPool, self::SystemHot, self::SystemCold, self::SystemSuspense];

        return $includeDemo ? [...$types, self::SystemDemoFaucet] : $types;
    }

    /**
     * @return list<self>
     */
    public static function custodyTypes(): array
    {
        return [self::SystemHot, self::SystemCold];
    }
}
