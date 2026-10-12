<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Support\Concerns\HasPublicUlid;
use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Merkle sum tree over every user's balance of one asset on one day.
 *
 * @property int $id
 * @property string $public_id
 * @property int $asset_id
 * @property CarbonImmutable $snapshot_date
 * @property string $root_hash
 * @property Decimal $total
 * @property int $leaf_count
 * @property CarbonImmutable|null $published_at
 */
final class LiabilityProof extends Model
{
    use HasPublicUlid;

    public const UPDATED_AT = null;

    protected $fillable = ['public_id', 'asset_id', 'snapshot_date', 'root_hash', 'total', 'leaf_count'];

    /**
     * @return HasMany<LiabilityProofLeaf, $this>
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(LiabilityProofLeaf::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'immutable_date',
            'total' => AsDecimal::class,
            'leaf_count' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }
}
