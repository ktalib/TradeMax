<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\ContactType;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\ContactChange;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\OneTimeCodeNotification;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Identity\Services\PhoneNumbers;
use App\Domain\Identity\Services\StepUpVerifier;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class RequestPhoneChange
{
    public function __construct(
        private readonly StepUpVerifier $stepUp,
        private readonly PhoneNumbers $phones,
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, StepUpCodes $codes, string $input, string $region): ContactChange
    {
        $phone = $this->phones->toE164($input, $region);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid mobile number. Landlines can\'t receive our security codes.')]);
        }

        if ($phone === $user->phone) {
            throw ValidationException::withMessages(['phone' => __('That is already your phone number.')]);
        }

        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => __('This phone number is already linked to an account.')]);
        }

        $this->stepUp->verify($user, $codes->totp, $codes->email);

        ContactChange::query()
            ->where('user_id', $user->id)
            ->where('type', ContactType::Phone->value)
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => CarbonImmutable::now()]);

        $change = ContactChange::query()->create([
            'user_id' => $user->id,
            'type' => ContactType::Phone,
            'new_value' => $phone,
            'expires_at' => CarbonImmutable::now()->addMinutes(OtpPurpose::PhoneChange->ttlMinutes()),
        ]);

        $code = $this->otp->issue($user, OtpPurpose::PhoneChange, OtpChannel::Sms, $phone);
        Notification::route('sms', $phone)->notify(new OneTimeCodeNotification($code, OtpPurpose::PhoneChange, OtpChannel::Sms));

        $this->audit->record('user.phone_change_requested', $user);

        return $change;
    }
}
