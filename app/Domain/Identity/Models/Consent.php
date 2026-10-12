<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $document
 * @property string $version
 * @property string|null $jurisdiction
 * @property CarbonImmutable $accepted_at
 * @property string|null $ip
 */
final class Consent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'document', 'version', 'jurisdiction', 'accepted_at', 'ip'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }
}
