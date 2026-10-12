<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\DTOs\WalletBalance;
use App\Domain\Ledger\DTOs\WalletRef;
use App\Domain\Ledger\Enums\WalletType;
use App\Support\Money\Casts\AsDecimal;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Balances are written only by LedgerService (query builder updates). Saving a changed
 * balance through the model throws, and wallets are never deleted.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $asset_id
 * @property WalletType $type
 * @property Decimal $available
 * @property Decimal $locked
 * @property int $version
 */
final class Wallet extends Model
{
    protected $fillable = ['user_id', 'asset_id', 'type'];

    protected static function booted(): void
    {
        self::updating(static function (self $wallet): void {
            if ($wallet->isDirty(['available', 'locked', 'version'])) {
                throw new LogicException('Wallet balances change only through LedgerService::post().');
            }
        });
        self::deleting(static fn (): never => throw new LogicException('Wallets cannot be deleted.'));
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function toRef(): WalletRef
    {
        return new WalletRef($this->id, $this->user_id, $this->asset_id, $this->type);
    }

    public function balance(): WalletBalance
    {
        return new WalletBalance($this->available, $this->locked);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
            'available' => AsDecimal::class,
            'locked' => AsDecimal::class,
            'version' => 'integer',
        ];
    }
}
