<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\ReconciliationStatus;
use App\Support\Concerns\HasPublicUlid;
use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $asset_id
 * @property ReconciliationStatus $status
 * @property Decimal $ledger_custody
 * @property Decimal|null $reported_custody
 * @property Decimal|null $difference
 * @property Decimal $tolerance
 * @property int|null $custody_balance_id
 * @property bool $withdrawals_halted
 * @property CarbonImmutable $created_at
 */
final class ReconciliationReport extends Model
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'asset_id', 'status', 'ledger_custody', 'reported_custody', 'difference', 'tolerance',
        'custody_balance_id', 'withdrawals_halted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'ledger_custody' => AsDecimal::class,
            'reported_custody' => AsDecimal::class,
            'difference' => AsDecimal::class,
            'tolerance' => AsDecimal::class,
            'withdrawals_halted' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }
}
