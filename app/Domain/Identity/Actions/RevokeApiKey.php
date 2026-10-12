<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\ApiKey;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SecurityEvents;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final class RevokeApiKey
{
    public function __construct(private readonly SecurityEvents $events) {}

    public function handle(User $user, ApiKey $apiKey): void
    {
        if ($apiKey->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if (! $apiKey->isActive()) {
            return;
        }

        $apiKey->forceFill(['revoked_at' => CarbonImmutable::now()])->save();

        $this->events->record($user, SecurityEvent::ApiKeyRevoked, ['key' => $apiKey->key_prefix.'…', 'name' => $apiKey->name]);
    }
}
