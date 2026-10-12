<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously: a code that arrives after the user gave up is useless.
 * SMS bodies stay under 160 characters and never contain links.
 */
final class OneTimeCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly OtpPurpose $purpose,
        public readonly OtpChannel $channel,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [$this->channel === OtpChannel::Sms ? SmsChannel::class : 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':app verification code: :code', ['app' => config('app.name'), 'code' => $this->code]))
            ->markdown('mail.one-time-code', [
                'code' => $this->code,
                'reason' => $this->reason(),
                'minutes' => $this->purpose->ttlMinutes(),
                'antiPhishingCode' => $notifiable instanceof User ? $notifiable->anti_phishing_code : null,
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return __(':app code: :code. Valid :minutes min. Never share it, not even with our staff.', [
            'app' => config('app.name'),
            'code' => $this->code,
            'minutes' => $this->purpose->ttlMinutes(),
        ]);
    }

    private function reason(): string
    {
        return match ($this->purpose) {
            OtpPurpose::PhoneVerification => __('to confirm your phone number'),
            OtpPurpose::NewDevice => __('to sign in on a new device'),
            OtpPurpose::StepUp => __('to approve a change to your account security'),
            OtpPurpose::PhoneChange => __('to confirm your new phone number'),
        };
    }
}
