<?php

declare(strict_types=1);

use App\Support\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Temporary: a real CREATE TABLE commits MySQL's open test transaction.
    Schema::create('ulid_probes', function (Blueprint $table): void {
        $table->temporary();
        $table->id();
        $table->ulid('public_id')->unique();
        $table->timestamps();
    });
});

function ulidProbe(): Model
{
    return new class extends Model
    {
        use HasPublicUlid;

        protected $table = 'ulid_probes';

        protected $fillable = ['public_id'];
    };
}

it('assigns a ULID public id on create', function (): void {
    $model = ulidProbe();
    $model->save();

    expect(Str::isUlid((string) $model->getAttribute('public_id')))->toBeTrue();
});

it('keeps a public id that was set explicitly', function (): void {
    $ulid = (string) Str::ulid();
    $model = ulidProbe()->fill(['public_id' => $ulid]);
    $model->save();

    expect($model->getAttribute('public_id'))->toBe($ulid);
});

it('routes by public id, not the internal key', function (): void {
    expect(ulidProbe()->getRouteKeyName())->toBe('public_id');
});
