<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Console;

use App\Domain\Ledger\Actions\TakeBalanceSnapshots;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SnapshotCommand extends Command
{
    protected $signature = 'ledger:snapshot {--date= : UTC day to close (Y-m-d); defaults to yesterday}';

    protected $description = 'Write end-of-day balance snapshots for every wallet.';

    public function handle(TakeBalanceSnapshots $snapshots): int
    {
        $option = $this->option('date');
        $date = is_string($option) && $option !== '' ? CarbonImmutable::parse($option, 'UTC') : null;

        $written = $snapshots->handle($date);
        $this->info("Wrote {$written} snapshots.");

        return self::SUCCESS;
    }
}
