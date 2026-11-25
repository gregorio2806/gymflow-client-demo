<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\GymInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GymInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Gym $gym,
        public string $role,
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
        return (new MailMessage)
            ->subject(__('You\'re invited to join :gym', ['gym' => $this->gym->name]))
            ->line(__(':gym invited you to join their team as :role.', ['gym' => $this->gym->name, 'role' => str_replace('-', ' ', $this->role)]))
            ->action(__('View invitation'), route('invitations.show', $this->token))
            ->line(__('This invitation expires in :days days. If you weren\'t expecting it, you can ignore this email.', [
                'days' => GymInvitation::VALID_FOR_DAYS,
            ]));
    }
}
