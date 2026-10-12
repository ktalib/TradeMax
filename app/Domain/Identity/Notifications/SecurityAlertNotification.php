<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Enums\SecurityEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Mandatory security alert (cannot be switched off). Every email carries the user's
 * anti-phishing code and a one-click "freeze my account" link.
 */
final class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $context
     * @param  string|null  $onlyChannel  'mail' or 'sms' when notifying a previous contact
     */
    public function __construct(
        public readonly User $user,
        public readonly SecurityEvent $event,
        public readonly array $context = [],
        public readonly ?string $onlyChannel = null,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($this->onlyChannel !== null) {
            return [$this->onlyChannel === 'sms' ? SmsChannel::class : 'mail'];
        }

        $channels = ['mail', 'database'];

        if ($this->event->sendsSms() && ! $notifiable instanceof AnonymousNotifiable) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->event->subject())
            ->markdown('mail.security-alert', [
                'user' => $this->user,
                'event' => $this->event,
                'context' => $this->context,
                'freezeUrl' => URL::temporarySignedRoute('account.freeze', now()->addDays(7), ['user' => $this->user->public_id]),
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return __(':app: :subject. Not you? Freeze your account from the email we sent, or contact support.', [
            'app' => config('app.name'),
            'subject' => $this->event->subject(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event->value,
            'title' => $this->event->subject(),
            'context' => $this->context,
        ];
    }
}
