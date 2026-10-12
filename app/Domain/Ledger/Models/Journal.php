<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\PostedByType;
use App\Support\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * A posted journal is permanent. Mistakes are corrected with a reversal journal.
 *
 * @property int $id
 * @property string $public_id
 * @property string $idempotency_key
 * @property string $fingerprint
 * @property string $reference_type
 * @property string $reference_id
 * @property string $description
 * @property int|null $reverses_journal_id
 * @property PostedByType $posted_by_type
 * @property int|null $posted_by_id
 * @property CarbonImmutable $created_at
 */
final class Journal extends Model
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'idempotency_key', 'fingerprint', 'reference_type', 'reference_id', 'description',
        'reverses_journal_id', 'posted_by_type', 'posted_by_id',
    ];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Posted journals are immutable; post a reversal.'));
        self::deleting(static fn (): never => throw new LogicException('Posted journals cannot be deleted; post a reversal.'));
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_id');
    }

    /**
     * @return HasOne<Journal, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_journal_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_by_type' => PostedByType::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
