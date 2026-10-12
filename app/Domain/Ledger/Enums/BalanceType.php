<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum BalanceType: string
{
    /** Free to trade, withdraw or transfer. */
    case Available = 'available';

    /** Reserved by an open order, pending withdrawal or similar. */
    case Locked = 'locked';
}
