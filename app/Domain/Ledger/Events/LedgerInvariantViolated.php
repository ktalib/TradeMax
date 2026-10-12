<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Events;

use App\Domain\Ledger\DTOs\InvariantViolation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Critical: the ledger no longer adds up. Notifications (Phase 6) pages on-call staff.
 */
final class LedgerInvariantViolated
{
    use Dispatchable;

    /**
     * @param  list<InvariantViolation>  $violations
     */
    public function __construct(public readonly array $violations) {}
}
