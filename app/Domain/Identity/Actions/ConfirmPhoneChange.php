<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\ContactType;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\ContactChange;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Identity\Services\SecurityEvents;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmPhoneChange
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly SecurityEvents $events,
    ) {}

    public function handle(User $user, string $code): void
    {
        $change = ContactChange::query()
            ->where('user_id', $user->id)
            ->where('type', ContactType::Phone->value)
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->latest('id')
            ->first();

        if ($change === null || ! $change->isPending()) {
            throw ValidationException::withMessages(['code' => __('There is no phone change waiting. Start again from the security page.')]);
        }

        if (! $this->otp->verify($user, OtpPurpose::PhoneChange, $code, $change->new_value)) {
            throw ValidationException::withMessages(['code' => __('That code is wrong or has expired. Request a new code and try again.')]);
        }

        if (User::query()->where('phone', $change->new_value)->exists()) {
            throw ValidationException::withMessages(['code' => __('This phone number is now linked to another account.')]);
        }

        $oldPhone = $user->phone;

        DB::transaction(function () use ($user, $change): void {
            $now = CarbonImmutable::now();
            $user->forceFill(['phone' => $change->new_value, 'phone_verified_at' => $now])->save();
            $change->forceFill(['confirmed_at' => $now])->save();
        });

        $this->events->record($user, SecurityEvent::PhoneChanged, alsoNotify: ['sms' => $oldPhone]);
    }
}
