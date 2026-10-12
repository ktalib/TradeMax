<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

/**
 * A balanced set of entries to post as one journal. `referenceType`/`referenceId` point at
 * the business record (deposit, trade…); `idempotencyKey` makes retries safe.
 */
final readonly class JournalDTO
{
    /**
     * @param  list<EntryDTO>  $entries
     */
    public function __construct(
        public string $referenceType,
        public string $referenceId,
        public string $idempotencyKey,
        public string $description,
        public array $entries,
        public PostedBy $postedBy,
        public ?int $reversesJournalId = null,
    ) {}

    /**
     * A stable hash of what the journal does, so a replayed key with different entries is
     * caught instead of silently returning the first journal.
     */
    public function fingerprint(): string
    {
        $lines = array_map(
            static fn (EntryDTO $e): string => implode('|', [$e->walletId, $e->assetId, $e->direction->value, $e->balanceType->value, $e->amount->toDatabase()]),
            $this->entries,
        );
        sort($lines);

        return hash('sha256', implode("\n", [$this->referenceType, $this->referenceId, (string) $this->reversesJournalId, ...$lines]));
    }
}
