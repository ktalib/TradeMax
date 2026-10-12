<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Domain\Compliance\Enums\CountryStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $name
 * @property CountryStatus $status
 * @property list<string>|null $features_disabled
 * @property string|null $notes
 */
final class Country extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'status', 'features_disabled', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CountryStatus::class,
            'features_disabled' => 'array',
        ];
    }
}
