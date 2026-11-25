<?php

namespace Tests\Feature\Tenancy;

use App\Models\OwnerInvitation;
use App\Models\User;
use App\Support\LegalDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_not_platform_admins_by_default()
    {
        $this->assertFalse(User::factory()->create()->refresh()->is_platform_admin);
    }

    public function test_platform_admin_state_sets_the_flag()
    {
        $this->assertTrue(User::factory()->platformAdmin()->create()->refresh()->is_platform_admin);
    }

    public function test_flag_cannot_be_set_when_signing_up_from_an_invitation()
    {
        $invitation = new OwnerInvitation(['email' => 'sneaky@example.com', 'plan' => 'starter']);
        $token = $invitation->issueToken();
        $invitation->save();

        $this->post(route('owner-invitations.register', $token), ['terms_accepted' => true, 'policy_version' => LegalDocuments::bundle()['version'],
            'name' => 'Sneaky',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_platform_admin' => true,
        ]);

        $this->assertFalse(User::where('email', 'sneaky@example.com')->first()->is_platform_admin);
    }

    public function test_flag_cannot_be_set_through_profile_update()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Sneaky',
            'email' => $user->email,
            'is_platform_admin' => true,
        ]);

        $this->assertFalse($user->refresh()->is_platform_admin);
    }
}
