<?php

namespace App\Notifications;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the platform admin that a gym owner asked for access.
 */
class AccessRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AccessRequest $accessRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->accessRequest;

        return (new MailMessage)
            ->subject(__('Access request: :gym (:plan)', ['gym' => $request->gym_name, 'plan' => $request->plan->label()]))
            ->replyTo($request->email, $request->name)
            ->line(__(':name asked for access.', ['name' => $request->name]))
            ->line(__('Gym: :gym', ['gym' => $request->gym_name]))
            ->line(__('Plan: :plan', ['plan' => $request->plan->label()]))
            ->line(__('Email: :email', ['email' => $request->email]))
            ->line(__('Phone: :phone', ['phone' => $request->phone ?? '—']))
            ->line(__('Location: :location', ['location' => $request->location ?? '—']))
            ->line(__('Message: :message', ['message' => $request->message ?? '—']))
            ->action(__('Review and send access'), route('platform.owner-invitations.index'));
    }
}
