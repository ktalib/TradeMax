<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\IssuedApiKey;
use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\ApiKeyPermission;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\ApiKey;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SecurityEvents;
use App\Domain\Identity\Services\StepUpVerifier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates an HMAC API key pair. The key is looked up by its SHA-256 hash; the secret is
 * stored encrypted because the server must recompute signatures (ADR-0007). Both are
 * returned once and can never be shown again.
 */
final class CreateApiKey
{
    public function __construct(
        private readonly StepUpVerifier $stepUp,
        private readonly SecurityEvents $events,
    ) {}

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $ipWhitelist
     */
    public function handle(User $user, StepUpCodes $codes, string $name, array $permissions, array $ipWhitelist): IssuedApiKey
    {
        $validated = Validator::make(
            ['name' => trim($name), 'permissions' => $permissions, 'ip_whitelist' => $ipWhitelist],
            [
                'name' => ['required', 'string', 'max:64'],
                'permissions' => ['required', 'array', 'min:1'],
                'permissions.*' => ['string', 'distinct', 'in:'.implode(',', array_column(ApiKeyPermission::cases(), 'value'))],
                'ip_whitelist' => ['array', 'max:20'],
                'ip_whitelist.*' => ['ip', 'distinct'],
            ],
        )->validate();

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages(['name' => __('API keys can\'t be created while your account is frozen.')]);
        }

        $active = ApiKey::query()->where('user_id', $user->id)->whereNull('revoked_at')->count();
        if ($active >= (int) config('platform.security.max_api_keys', 10)) {
            throw ValidationException::withMessages(['name' => __('You have the maximum number of API keys. Revoke one you no longer use first.')]);
        }

        $perms = array_map(static fn (string $p): ApiKeyPermission => ApiKeyPermission::from($p), (array) $validated['permissions']);

        if (in_array(ApiKeyPermission::Trade, $perms, true) && ! (bool) data_get(config('platform.kyc_tiers'), $user->kyc_tier.'.can_api_trade', false)) {
            throw ValidationException::withMessages(['permissions' => __('Trading through the API needs identity verification level 3.')]);
        }

        $this->stepUp->verify($user, $codes->totp, $codes->email);

        $plainKey = 'tmk_'.Str::random(40);
        $plainSecret = Str::random(64);

        $apiKey = ApiKey::query()->create([
            'user_id' => $user->id,
            'name' => (string) $validated['name'],
            'key_prefix' => substr($plainKey, 0, 12),
            'key_hash' => hash('sha256', $plainKey),
            'secret' => $plainSecret,
            'permissions' => $perms,
            'ip_whitelist' => array_values((array) ($validated['ip_whitelist'] ?? [])) ?: null,
        ]);

        $this->events->record($user, SecurityEvent::ApiKeyCreated, ['key' => $apiKey->key_prefix.'…', 'name' => $apiKey->name]);

        return new IssuedApiKey($apiKey, $plainKey, $plainSecret);
    }
}
