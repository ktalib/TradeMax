<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Balance at the end of a UTC day, written once by `ledger:snapshot`.
 *
 * @property int $id
 * @property int $wallet_id
 * @property CarbonImmutable $snapshot_date
 * @property Decimal $available
 * @property Decimal $locked
 */
final class WalletBalanceSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'immutable_date',
            'available' => AsDecimal::class,
            'locked' => AsDecimal::class,
        ];
    }
}
