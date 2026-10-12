<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum CustodySource: string
{
    /** Entered by finance from wallet/exchange statements. */
    case Manual = 'manual';

    /** Read from the payment gateway's balance API (Phase 4). */
    case Gateway = 'gateway';
}
