<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\JournalDTO;
use App\Domain\Ledger\DTOs\PostedBy;
use App\Domain\Ledger\DTOs\PostedJournal;
use App\Domain\Ledger\Exceptions\CannotReverseJournal;
use App\Domain\Ledger\Models\Journal;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a posted journal with a mirror-image journal. The original is never touched.
 * A journal can be reversed once; a reversal can't be reversed (post a new journal).
 * Fails with InsufficientBalance when the funds have since been spent.
 */
final class ReverseJournal
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(string $journalPublicId, string $reason, PostedBy $postedBy, ?Authenticatable $actor = null): PostedJournal
    {
        $original = Journal::query()->where('public_id', $journalPublicId)->firstOrFail();

        if ($original->reverses_journal_id !== null) {
            throw CannotReverseJournal::isAReversal($original->public_id);
        }

        if (Journal::query()->where('reverses_journal_id', $original->id)->exists()) {
            throw CannotReverseJournal::alreadyReversed($original->public_id);
        }

        $entries = array_values(JournalEntry::query()->where('journal_id', $original->id)->orderBy('id')->get()
            ->map(static fn (JournalEntry $e): EntryDTO => (new EntryDTO($e->wallet_id, $e->asset_id, $e->direction, $e->amount, $e->balance_type))->mirrored())
            ->all());

        return DB::transaction(function () use ($original, $entries, $reason, $postedBy, $actor): PostedJournal {
            $posted = $this->ledger->post(new JournalDTO(
                referenceType: 'journal_reversal',
                referenceId: $original->public_id,
                idempotencyKey: 'reversal:'.$original->id,
                description: 'Reversal: '.$reason,
                entries: $entries,
                postedBy: $postedBy,
                reversesJournalId: $original->id,
            ));

            if (! $posted->replayed) {
                $this->audit->record('ledger.journal_reversed', $original, null, [
                    'reversal_journal' => $posted->publicId,
                    'reason' => $reason,
                ], $actor);
            }

            return $posted;
        });
    }
}
