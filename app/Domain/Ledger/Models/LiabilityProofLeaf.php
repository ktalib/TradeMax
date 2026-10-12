<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $liability_proof_id
 * @property int $position
 * @property int $user_id
 * @property Decimal $balance
 * @property string $nonce
 * @property string $leaf_hash
 */
final class LiabilityProofLeaf extends Model
{
    public $timestamps = false;

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'balance' => AsDecimal::class,
        ];
    }
}
