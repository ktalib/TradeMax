<?php

declare(strict_types=1);

/*
| Child process for the ledger concurrency tests (tests/Concurrency). Boots the app against
| the test database, waits at the start barrier so all workers hit the ledger at once,
| posts one journal and prints the outcome as JSON.
|
| argv[1]: base64 JSON {key, reference, entries: [{wallet, asset, direction, amount, balance_type}]}
*/

use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\JournalDTO;
use App\Domain\Ledger\DTOs\PostedBy;
use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Services\LedgerService;
use App\Support\Money\Decimal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\ParallelRunner;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Never let a stray config cache point a worker at a real database.
$expected = getenv('LEDGER_WORKER_DATABASE');
if (config('database.default') !== 'mysql' || $expected === false || config('database.connections.mysql.database') !== $expected) {
    fwrite(STDERR, 'Refusing to run: not connected to the expected test database.');
    exit(2);
}

/** @var array{key: string, reference: string, entries: list<array{wallet: int, asset: int, direction: string, amount: string, balance_type: string}>} $payload */
$payload = json_decode((string) base64_decode($argv[1] ?? '', true), true, flags: JSON_THROW_ON_ERROR);

DB::connection()->getPdo();

$entries = array_map(static fn (array $e): EntryDTO => new EntryDTO(
    $e['wallet'],
    $e['asset'],
    EntryDirection::from($e['direction']),
    Decimal::of($e['amount']),
    BalanceType::from($e['balance_type']),
), $payload['entries']);

ParallelRunner::awaitStart();
$began = microtime(true);

try {
    $posted = $app->make(LedgerService::class)->post(new JournalDTO(
        referenceType: 'concurrency_test',
        referenceId: $payload['reference'],
        idempotencyKey: $payload['key'],
        description: 'Concurrency test',
        entries: $entries,
        postedBy: PostedBy::system(),
    ));

    echo json_encode(['ok' => true, 'journal_id' => $posted->id, 'replayed' => $posted->replayed, 'began' => $began, 'ended' => microtime(true)]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e::class, 'message' => $e->getMessage(), 'began' => $began, 'ended' => microtime(true)]);
}
