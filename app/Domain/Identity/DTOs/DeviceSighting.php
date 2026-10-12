<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Models\UserDevice;

final readonly class DeviceSighting
{
    public function __construct(
        public UserDevice $device,
        public bool $isNewDevice,
        public bool $ipChanged,
    ) {}

    /**
     * Email OTP is required unless the device is trusted, or it is already verified and
     * signing in from the same IP as last time.
     */
    public function needsVerification(): bool
    {
        if ($this->device->isTrusted()) {
            return false;
        }

        return $this->device->verified_at === null || $this->ipChanged;
    }

    /**
     * New devices and new IPs always produce a security alert, even on trusted devices.
     */
    public function warrantsAlert(): bool
    {
        return $this->isNewDevice || $this->ipChanged;
    }
}
