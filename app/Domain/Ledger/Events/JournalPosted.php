<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per new journal, after the outermost transaction commits. Not fired on replay.
 */
final class JournalPosted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $walletIds
     */
    public function __construct(
        public readonly int $journalId,
        public readonly string $referenceType,
        public readonly string $referenceId,
        public readonly array $walletIds,
    ) {}
}
