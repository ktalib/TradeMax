<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\DTOs\DeviceSighting;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserDevice;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Identifies a browser by a random token in a long-lived, encrypted, HTTP-only cookie.
 * Only a hash of the token is stored. Clearing cookies makes the browser a new device.
 */
final class DeviceRecognizer
{
    public const COOKIE = 'tm_device';

    public const SESSION_KEY = 'device.verified_id';

    private const TOKEN_LENGTH = 40;

    private const COOKIE_MINUTES = 60 * 24 * 365 * 2;

    public function token(Request $request): string
    {
        $token = $request->cookie(self::COOKIE);

        if (is_string($token) && strlen($token) === self::TOKEN_LENGTH) {
            return $token;
        }

        $token = Str::random(self::TOKEN_LENGTH);
        Cookie::queue(Cookie::make(self::COOKIE, $token, self::COOKIE_MINUTES, '/', null, $request->isSecure(), true, false, 'lax'));
        $request->cookies->set(self::COOKIE, $token);

        return $token;
    }

    public function sight(User $user, Request $request): DeviceSighting
    {
        $hash = hash('sha256', $this->token($request));
        $now = CarbonImmutable::now();

        $device = UserDevice::query()->firstOrNew(['user_id' => $user->id, 'fingerprint_hash' => $hash]);
        $isNew = ! $device->exists;
        $ipChanged = ! $isNew && $device->ip !== $request->ip();

        $device->fill([
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'ip' => $request->ip(),
            'last_seen_at' => $now,
        ]);

        if ($isNew) {
            $device->first_seen_at = $now;
        }

        $device->save();

        return new DeviceSighting($device, $isNew, $ipChanged);
    }

    public function markVerified(UserDevice $device, bool $trust): void
    {
        $now = CarbonImmutable::now();
        $device->verified_at = $now;

        if ($trust) {
            $device->trusted_at = $now;
            $device->trusted_until = $now->addDays((int) config('platform.security.trusted_device_days', 30));
        }

        $device->save();
    }

    public function current(User $user, Request $request): ?UserDevice
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token)) {
            return null;
        }

        return UserDevice::query()
            ->where('user_id', $user->id)
            ->where('fingerprint_hash', hash('sha256', $token))
            ->first();
    }
}
