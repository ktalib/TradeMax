<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\StepUpCodes;
use App\Domain\Identity\Enums\ContactType;
use App\Domain\Identity\Models\ContactChange;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ConfirmNewEmailNotification;
use App\Domain\Identity\Services\StepUpVerifier;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class RequestEmailChange
{
    public function __construct(
        private readonly StepUpVerifier $stepUp,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, StepUpCodes $codes, string $newEmail): ContactChange
    {
        $newEmail = Str::lower(trim($newEmail));

        Validator::make(['email' => $newEmail], [
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique(User::class, 'email'), Rule::notIn([$user->email])],
        ], ['email.not_in' => __('That is already your email address.')])->validate();

        $this->stepUp->verify($user, $codes->totp, $codes->email);

        ContactChange::query()
            ->where('user_id', $user->id)
            ->where('type', ContactType::Email->value)
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => CarbonImmutable::now()]);

        $change = ContactChange::query()->create([
            'user_id' => $user->id,
            'type' => ContactType::Email,
            'new_value' => $newEmail,
            'expires_at' => CarbonImmutable::now()->addMinutes(60),
        ]);

        Notification::route('mail', $newEmail)->notify(new ConfirmNewEmailNotification($change));

        $this->audit->record('user.email_change_requested', $user);

        return $change;
    }
}
