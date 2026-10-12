<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives a model a ULID `public_id` for URLs and APIs, keeping the bigint id internal.
 *
 * @mixin Model
 */
trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(static function (Model $model): void {
            if (blank($model->getAttribute('public_id'))) {
                $model->setAttribute('public_id', (string) Str::ulid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
