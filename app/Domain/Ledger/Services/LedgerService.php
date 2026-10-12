<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Assets\Repositories\Contracts\AssetCatalog;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\JournalDTO;
use App\Domain\Ledger\DTOs\PostedJournal;
use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\WalletType;
use App\Domain\Ledger\Events\JournalPosted;
use App\Domain\Ledger\Exceptions\IdempotencyConflict;
use App\Domain\Ledger\Exceptions\InsufficientBalance;
use App\Domain\Ledger\Exceptions\InvalidJournal;
use App\Domain\Ledger\Models\Journal;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The only code that changes wallet balances (AGENTS.md, ADR-0003).
 *
 * post() runs in one transaction:
 *   1. replay check: a known idempotency key returns the existing journal;
 *   2. row-lock every wallet whose balance must be checked, in ascending id order;
 *   3. insert the journal (its unique key catches a concurrent replay), then validate
 *      the sign convention against the locked balances;
 *   4. apply balances (locked rows first, then hot system rows by atomic increment, also
 *      in id order) and insert entries with balance_after;
 *   5. JournalPosted fires after the outermost commit.
 *
 * Hot rows (system_hot, and system_fee when only credited) are never read with a lock, so
 * concurrent journals in every market only contend on them for the short tail of the
 * transaction. Deadlocks that slip through are retried.
 */
final class LedgerService
{
    private const DEADLOCK_ATTEMPTS = 3;

    public function __construct(private readonly AssetCatalog $assets) {}

    /**
     * @throws InsufficientBalance when a wallet that can't go negative would
     * @throws IdempotencyConflict when the key was used for different entries
     * @throws InvalidJournal when the journal is malformed (a caller bug)
     */
    public function post(JournalDTO $journal): PostedJournal
    {
        $this->assertWellFormed($journal);
        $fingerprint = $journal->fingerprint();

        try {
            return DB::transaction(fn (): PostedJournal => $this->write($journal, $fingerprint), self::DEADLOCK_ATTEMPTS);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent post with the same key committed first.
            return $this->existing($journal->idempotencyKey, $fingerprint, lock: true) ?? throw $e;
        }
    }

    private function write(JournalDTO $dto, string $fingerprint): PostedJournal
    {
        $replay = $this->existing($dto->idempotencyKey, $fingerprint, lock: false);
        if ($replay !== null) {
            return $replay;
        }

        $net = $this->netByWallet($dto->entries);
        $walletIds = array_keys($net);
        sort($walletIds);

        $wallets = $this->walletMeta($walletIds);
        foreach ($dto->entries as $entry) {
            $walletAsset = $wallets[$entry->walletId]['asset_id'];
            if ($walletAsset !== $entry->assetId) {
                throw InvalidJournal::assetMismatch($entry->walletId, $entry->assetId, $walletAsset);
            }
        }

        $lockIds = array_values(array_filter($walletIds, fn (int $id): bool => $this->needsLock($wallets[$id]['type'], $net[$id])));
        $hotIds = array_values(array_diff($walletIds, $lockIds));

        $current = $this->lockWallets($lockIds);

        // Insert the journal before checking balances: if a concurrent post with this key
        // committed while we waited for the locks, the unique key fails here and post()
        // returns that journal, instead of this replay failing on funds the first one spent.
        $now = CarbonImmutable::now();
        $journal = Journal::query()->create([
            'idempotency_key' => $dto->idempotencyKey,
            'fingerprint' => $fingerprint,
            'reference_type' => $dto->referenceType,
            'reference_id' => $dto->referenceId,
            'description' => mb_substr($dto->description, 0, 255),
            'reverses_journal_id' => $dto->reversesJournalId,
            'posted_by_type' => $dto->postedBy->type,
            'posted_by_id' => $dto->postedBy->id,
        ]);

        $final = [];
        foreach ($lockIds as $id) {
            $final[$id] = $this->applyChecked($id, $wallets[$id]['type'], $current[$id], $net[$id]);
        }

        foreach ($lockIds as $id) {
            DB::table('wallets')->where('id', $id)->update([
                'available' => $final[$id][BalanceType::Available->value]->toDatabase(),
                'locked' => $final[$id][BalanceType::Locked->value]->toDatabase(),
                'version' => DB::raw('version + 1'),
                'updated_at' => $now,
            ]);
        }

        foreach ($hotIds as $id) {
            $final[$id] = $this->applyAtomically($id, $net[$id], $now);
        }

        DB::table('journal_entries')->insert($this->entryRows($journal->id, $dto->entries, $net, $final, $now));

        JournalPosted::dispatch($journal->id, $dto->referenceType, $dto->referenceId, $walletIds);

        return new PostedJournal($journal->id, $journal->public_id, $dto->idempotencyKey, replayed: false);
    }

    private function assertWellFormed(JournalDTO $dto): void
    {
        if (trim($dto->idempotencyKey) === '') {
            throw InvalidJournal::emptyKey();
        }

        if (count($dto->entries) < 2) {
            throw InvalidJournal::tooFewEntries();
        }

        /** @var array<int, array{debits: Decimal, credits: Decimal}> $byAsset */
        $byAsset = [];

        foreach ($dto->entries as $entry) {
            if (! $entry->amount->isPositive()) {
                throw InvalidJournal::nonPositiveAmount($entry->walletId);
            }

            $byAsset[$entry->assetId] ??= ['debits' => Decimal::zero(), 'credits' => Decimal::zero()];
            $side = $entry->direction === EntryDirection::Debit ? 'debits' : 'credits';
            $byAsset[$entry->assetId][$side] = $byAsset[$entry->assetId][$side]->add($entry->amount);
        }

        foreach ($byAsset as $assetId => $sums) {
            if (! $sums['debits']->eq($sums['credits'])) {
                throw InvalidJournal::unbalanced($assetId, $sums['debits']->toString(), $sums['credits']->toString());
            }

            $precision = $this->assets->find($assetId)->precision;
            foreach ($dto->entries as $entry) {
                if ($entry->assetId === $assetId && ! $entry->amount->toAssetPrecision($precision)->eq($entry->amount)) {
                    throw InvalidJournal::tooPrecise($entry->walletId, $entry->amount->toString(), $precision);
                }
            }
        }
    }

    /**
     * @param  list<EntryDTO>  $entries
     * @return array<int, array<string, Decimal>> wallet id => balance type => signed net change
     */
    private function netByWallet(array $entries): array
    {
        $net = [];

        foreach ($entries as $entry) {
            $net[$entry->walletId] ??= [BalanceType::Available->value => Decimal::zero(), BalanceType::Locked->value => Decimal::zero()];
            $net[$entry->walletId][$entry->balanceType->value] = $net[$entry->walletId][$entry->balanceType->value]->add($entry->signedAmount());
        }

        return $net;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{asset_id: int, type: WalletType}>
     */
    private function walletMeta(array $ids): array
    {
        $rows = DB::table('wallets')->whereIn('id', $ids)->get(['id', 'asset_id', 'type'])->keyBy('id');
        $meta = [];

        foreach ($ids as $id) {
            $row = $rows->get($id);
            if (! $row instanceof stdClass) {
                throw InvalidJournal::unknownWallet($id);
            }

            $meta[$id] = ['asset_id' => $this->int($row->asset_id), 'type' => WalletType::from($this->string($row->type))];
        }

        return $meta;
    }

    /**
     * A wallet needs a lock whenever its balance must be checked before writing. Hot system
     * rows skip it when the change can't break the sign convention (ADR-0003).
     *
     * @param  array<string, Decimal>  $net
     */
    private function needsLock(WalletType $type, array $net): bool
    {
        if (! $type->isHotRow()) {
            return true;
        }

        if ($type->allowsNegative()) {
            return false;
        }

        foreach ($net as $change) {
            if ($change->isNegative()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $ids  ascending
     * @return array<int, array<string, Decimal>>
     */
    private function lockWallets(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('wallets')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id', 'available', 'locked']);
        $balances = [];

        foreach ($rows as $row) {
            $balances[$this->int($row->id)] = [
                BalanceType::Available->value => Decimal::of($this->string($row->available)),
                BalanceType::Locked->value => Decimal::of($this->string($row->locked)),
            ];
        }

        return $balances;
    }

    /**
     * @param  array<string, Decimal>  $current
     * @param  array<string, Decimal>  $net
     * @return array<string, Decimal>
     */
    private function applyChecked(int $walletId, WalletType $type, array $current, array $net): array
    {
        $final = [];

        foreach ($current as $balanceType => $balance) {
            $after = $balance->add($net[$balanceType]);

            if ($after->isNegative() && ! $type->allowsNegative()) {
                throw new InsufficientBalance($walletId, BalanceType::from($balanceType), $balance, $net[$balanceType]->negate());
            }

            $final[$balanceType] = $after;
        }

        return $final;
    }

    /**
     * One UPDATE per hot row, computed by the database. The CASTs keep MySQL in DECIMAL
     * arithmetic; a bare string parameter would be added as a double.
     *
     * @param  array<string, Decimal>  $net
     * @return array<string, Decimal>
     */
    private function applyAtomically(int $walletId, array $net, CarbonImmutable $now): array
    {
        DB::update(
            'update wallets set available = available + cast(? as decimal(36,18)), locked = locked + cast(? as decimal(36,18)), version = version + 1, updated_at = ? where id = ?',
            [$net[BalanceType::Available->value]->toDatabase(), $net[BalanceType::Locked->value]->toDatabase(), $now, $walletId],
        );

        // We hold this row's write lock until commit, so this reads our own result.
        $row = DB::table('wallets')->where('id', $walletId)->first(['available', 'locked']);
        assert($row instanceof stdClass);

        return [
            BalanceType::Available->value => Decimal::of($this->string($row->available)),
            BalanceType::Locked->value => Decimal::of($this->string($row->locked)),
        ];
    }

    /**
     * Rows for journal_entries, each with the wallet's balance right after that line.
     *
     * @param  list<EntryDTO>  $entries
     * @param  array<int, array<string, Decimal>>  $net
     * @param  array<int, array<string, Decimal>>  $final
     * @return list<array<string, mixed>>
     */
    private function entryRows(int $journalId, array $entries, array $net, array $final, CarbonImmutable $now): array
    {
        // Walk forward from the balance before this journal.
        $running = [];
        foreach ($final as $walletId => $balances) {
            foreach ($balances as $balanceType => $balance) {
                $running[$walletId][$balanceType] = $balance->sub($net[$walletId][$balanceType]);
            }
        }

        $rows = [];
        foreach ($entries as $entry) {
            $running[$entry->walletId][$entry->balanceType->value] = $running[$entry->walletId][$entry->balanceType->value]->add($entry->signedAmount());

            $rows[] = [
                'journal_id' => $journalId,
                'wallet_id' => $entry->walletId,
                'asset_id' => $entry->assetId,
                'direction' => $entry->direction->value,
                'balance_type' => $entry->balanceType->value,
                'amount' => $entry->amount->toDatabase(),
                'balance_after' => $running[$entry->walletId][$entry->balanceType->value]->toDatabase(),
                'created_at' => $now,
            ];
        }

        return $rows;
    }

    private function existing(string $key, string $fingerprint, bool $lock): ?PostedJournal
    {
        $query = DB::table('journals')->where('idempotency_key', $key);
        if ($lock) {
            // A locking read sees the latest committed row, whatever the snapshot.
            $query->sharedLock();
        }

        $row = $query->first(['id', 'public_id', 'fingerprint']);
        if (! $row instanceof stdClass) {
            return null;
        }

        if (! hash_equals($this->string($row->fingerprint), $fingerprint)) {
            throw IdempotencyConflict::forKey($key);
        }

        return new PostedJournal($this->int($row->id), $this->string($row->public_id), $key, replayed: true);
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
