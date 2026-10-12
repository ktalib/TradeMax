<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one asset moves on one network: contract, limits, fee and confirmations.
 *
 * @property int $id
 * @property int $asset_id
 * @property int $network_id
 * @property string|null $contract_address
 * @property bool $deposit_enabled
 * @property bool $withdraw_enabled
 * @property Decimal $min_deposit
 * @property Decimal $min_withdraw
 * @property Decimal $withdraw_fee
 * @property string|null $platform_deposit_address
 * @property bool $memo_required
 * @property int|null $required_confirmations
 * @property-read Asset $asset
 * @property-read Network $network
 */
final class AssetNetwork extends Model
{
    protected $fillable = [
        'asset_id', 'network_id', 'contract_address', 'deposit_enabled', 'withdraw_enabled', 'min_deposit',
        'min_withdraw', 'withdraw_fee', 'platform_deposit_address', 'memo_required', 'required_confirmations',
    ];

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Network, $this>
     */
    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    public function confirmations(): int
    {
        return $this->required_confirmations ?? $this->network->required_confirmations;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deposit_enabled' => 'boolean',
            'withdraw_enabled' => 'boolean',
            'min_deposit' => AsDecimal::class,
            'min_withdraw' => AsDecimal::class,
            'withdraw_fee' => AsDecimal::class,
            'memo_required' => 'boolean',
            'required_confirmations' => 'integer',
        ];
    }
}
