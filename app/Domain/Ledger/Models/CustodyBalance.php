<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\CustodySource;
use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * What custody really holds for an asset at a moment. Append-only: a correction is a
 * newer row.
 *
 * @property int $id
 * @property int $asset_id
 * @property CustodySource $source
 * @property Decimal $balance
 * @property CarbonImmutable $as_of
 * @property int|null $recorded_by_admin_id
 * @property string|null $note
 */
final class CustodyBalance extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['asset_id', 'source', 'balance', 'as_of', 'recorded_by_admin_id', 'note'];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Custody balances are append-only.'));
        self::deleting(static fn (): never => throw new LogicException('Custody balances are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => CustodySource::class,
            'balance' => AsDecimal::class,
            'as_of' => 'immutable_datetime',
        ];
    }
}
