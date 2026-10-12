<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum ReconciliationStatus: string
{
    /** Custody matches the ledger within tolerance. */
    case Matched = 'matched';

    /** Difference beyond tolerance: withdrawals of the asset are halted. */
    case Mismatch = 'mismatch';

    /** No custody figure has been recorded for the asset yet. */
    case Missing = 'missing';
}
