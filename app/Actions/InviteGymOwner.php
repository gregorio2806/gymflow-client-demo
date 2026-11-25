<?php

namespace App\Actions;

use App\Enums\Plan;
use App\Models\OwnerInvitation;
use App\Notifications\OwnerInvitationNotification;
use Illuminate\Support\Facades\Notification;

class InviteGymOwner
{
    /**
     * Platform admin grants access: email a one-time link to set up an account and create a gym.
     * Re-inviting the same email replaces their pending invitation.
     */
    public function handle(string $email, Plan $plan, ?string $gymName = null): OwnerInvitation
    {
        $email = mb_strtolower(trim($email));

        OwnerInvitation::where('email', $email)->whereNull('accepted_at')->delete();

        $invitation = new OwnerInvitation(['email' => $email, 'plan' => $plan, 'gym_name' => $gymName]);
        $invitation->invited_by = auth()->id();
        $token = $invitation->issueToken();
        $invitation->save();

        Notification::route('mail', $email)->notify(new OwnerInvitationNotification($plan->label(), $token));

        return $invitation;
    }
}
