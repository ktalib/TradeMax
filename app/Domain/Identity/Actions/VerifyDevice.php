<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DeviceRecognizer;
use App\Domain\Identity\Services\OtpService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class VerifyDevice
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly DeviceRecognizer $devices,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, Request $request, string $code, bool $trust): void
    {
        if (! $this->otp->verify($user, OtpPurpose::NewDevice, $code, $user->email)) {
            throw ValidationException::withMessages(['code' => __('That code is wrong or has expired. Request a new code and try again.')]);
        }

        $device = $this->devices->current($user, $request) ?? $this->devices->sight($user, $request)->device;
        $this->devices->markVerified($device, $trust);

        $request->session()->put(DeviceRecognizer::SESSION_KEY, $device->id);

        $this->audit->record('user.device_verified', $device, after: ['trusted' => $trust], actor: $user);
    }
}
