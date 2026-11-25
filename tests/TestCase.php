<?php

namespace Tests;

use App\Enums\Plan;
use App\Models\OwnerInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Sign-up is invite-only: give this user an accepted, unused owner invitation
     * (what the platform admin's access email + the owner's click produce), so they can create a gym.
     */
    protected function grantGymAccess(User $user, Plan $plan = Plan::Starter, ?string $gymName = null): OwnerInvitation
    {
        $invitation = new OwnerInvitation(['email' => $user->email, 'plan' => $plan, 'gym_name' => $gymName]);
        $invitation->issueToken();
        $invitation->forceFill(['user_id' => $user->id, 'accepted_at' => now()])->save();

        return $invitation;
    }
}
