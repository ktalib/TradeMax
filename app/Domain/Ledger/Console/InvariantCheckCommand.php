<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Console;

use App\Domain\Ledger\Actions\CheckLedgerInvariants;
use App\Domain\Ledger\DTOs\InvariantViolation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class InvariantCheckCommand extends Command
{
    protected $signature = 'ledger:invariant-check {--since= : only check journals posted after this time (balances are always checked in full)}';

    protected $description = 'Check that the ledger balances and no wallet breaks the sign convention.';

    public function handle(CheckLedgerInvariants $check): int
    {
        $option = $this->option('since');
        $since = is_string($option) && $option !== '' ? CarbonImmutable::parse($option, 'UTC') : null;

        $violations = $check->handle($since);

        if ($violations === []) {
            $this->info('Ledger invariants hold.');

            return self::SUCCESS;
        }

        $this->error(count($violations).' ledger invariant violation(s):');
        $this->table(['Rule', 'Detail'], array_map(
            static fn (InvariantViolation $v): array => [$v->rule, $v->message],
            $violations,
        ));

        return self::FAILURE;
    }
}
