<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\ContactChange;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the NEW address. The change only happens when this link is opened while signed in.
 */
final class ConfirmNewEmailNotification extends Notification
{
    public function __construct(public readonly ContactChange $change) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('security.email.confirm', $this->change->expires_at, ['change' => $this->change->public_id]);

        return (new MailMessage)
            ->subject(__('Confirm your new email address'))
            ->line(__('Someone asked to use this address for a :app account. If that was you, confirm it below while signed in.', ['app' => config('app.name')]))
            ->action(__('Confirm new email'), $url)
            ->line(__('The link works for 60 minutes. If you did not ask for this, ignore this email; nothing will change.'));
    }
}
