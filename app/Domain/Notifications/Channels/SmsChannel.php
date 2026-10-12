<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Contracts\SmsSender;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * Notification channel "sms". Notifications implement toSms($notifiable): string.
 */
final class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            throw new InvalidArgumentException(sprintf('%s has no toSms() method.', $notification::class));
        }

        $to = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('sms')
            : (method_exists($notifiable, 'routeNotificationForSms') ? $notifiable->routeNotificationForSms() : null);

        if (! is_string($to) || $to === '') {
            return;
        }

        $message = $notification->toSms($notifiable);

        $this->sender->send($to, is_string($message) ? mb_substr($message, 0, 160) : '');
    }
}
