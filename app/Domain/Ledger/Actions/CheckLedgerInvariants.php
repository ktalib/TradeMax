<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\DTOs\InvariantViolation;
use App\Domain\Ledger\Events\LedgerInvariantViolated;
use App\Domain\Ledger\Services\LedgerInvariants;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

final class CheckLedgerInvariants
{
    public function __construct(
        private readonly LedgerInvariants $invariants,
        private readonly LoggerInterface $log,
    ) {}

    /**
     * @return list<InvariantViolation>
     */
    public function handle(?CarbonImmutable $journalsSince = null): array
    {
        $violations = $this->invariants->check($journalsSince);

        if ($violations !== []) {
            $this->log->critical('Ledger invariants violated', [
                'count' => count($violations),
                'rules' => array_values(array_unique(array_map(static fn (InvariantViolation $v): string => $v->rule, $violations))),
            ]);

            LedgerInvariantViolated::dispatch($violations);
        }

        return $violations;
    }
}
