<?php

namespace App\Notifications;

use App\Models\OwnerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $planName,
        public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject(__('Your :app account is ready', ['app' => $app]))
            ->line(__('You\'ve been given access to :app on the :plan plan.', ['app' => $app, 'plan' => $this->planName]))
            ->line(__('Click below to set your password and create your gym.'))
            ->action(__('Set up my account'), route('owner-invitations.show', $this->token))
            ->line(__('This link expires in :days days and works once. If you didn\'t ask for access, you can ignore this email.', [
                'days' => OwnerInvitation::VALID_FOR_DAYS,
            ]));
    }
}
