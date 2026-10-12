<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $explorer_tx_url
 * @property string|null $explorer_address_url
 * @property int $required_confirmations
 * @property bool $is_active
 */
final class Network extends Model
{
    protected $fillable = ['code', 'name', 'explorer_tx_url', 'explorer_address_url', 'required_confirmations', 'is_active'];

    public function explorerTxUrl(string $txHash): ?string
    {
        return $this->explorer_tx_url === null ? null : str_replace('{tx}', rawurlencode($txHash), $this->explorer_tx_url);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_confirmations' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
