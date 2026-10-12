<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $symbol
 * @property string $name
 * @property int $precision
 * @property int $display_precision
 * @property string|null $icon_path
 * @property bool $is_fiat
 * @property bool $is_active
 * @property int $sort
 * @property Decimal $reconcile_tolerance
 * @property CarbonImmutable|null $withdrawals_halted_at
 * @property string|null $withdrawals_halt_reason
 */
final class Asset extends Model
{
    protected $fillable = [
        'symbol', 'name', 'precision', 'display_precision', 'icon_path', 'is_fiat', 'is_active', 'sort', 'reconcile_tolerance',
    ];

    /**
     * @return HasMany<AssetNetwork, $this>
     */
    public function assetNetworks(): HasMany
    {
        return $this->hasMany(AssetNetwork::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precision' => 'integer',
            'display_precision' => 'integer',
            'is_fiat' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
            'reconcile_tolerance' => AsDecimal::class,
            'withdrawals_halted_at' => 'immutable_datetime',
        ];
    }
}
