<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\BalanceType;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $journal_id
 * @property int $wallet_id
 * @property int $asset_id
 * @property EntryDirection $direction
 * @property BalanceType $balance_type
 * @property Decimal $amount
 * @property Decimal $balance_after
 * @property CarbonImmutable $created_at
 * @property-read Journal $journal
 * @property-read Wallet $wallet
 */
final class JournalEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Journal entries are immutable.'));
        self::deleting(static fn (): never => throw new LogicException('Journal entries cannot be deleted.'));
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => EntryDirection::class,
            'balance_type' => BalanceType::class,
            'amount' => AsDecimal::class,
            'balance_after' => AsDecimal::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
