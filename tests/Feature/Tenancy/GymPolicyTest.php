<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    public function test_owner_can_view_update_and_manage_staff()
    {
        $owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();

        $this->assertTrue($owner->can('view', $this->gym));
        $this->assertTrue($owner->can('update', $this->gym));
        $this->assertTrue($owner->can('manageStaff', $this->gym));
    }

    public function test_staff_and_coaches_can_view_but_not_change_the_gym()
    {
        foreach ([GymRole::Staff, GymRole::Coach] as $role) {
            $user = User::factory()->memberOf($this->gym, $role)->create();

            $this->assertTrue($user->can('view', $this->gym), $role->value);
            $this->assertFalse($user->can('update', $this->gym), $role->value);
            $this->assertFalse($user->can('manageStaff', $this->gym), $role->value);
        }
    }

    public function test_owner_of_another_gym_has_no_access()
    {
        $otherOwner = User::factory()->memberOf(Gym::factory()->create(), GymRole::Owner)->create();

        $this->assertFalse($otherOwner->can('view', $this->gym));
        $this->assertFalse($otherOwner->can('update', $this->gym));
        $this->assertFalse($otherOwner->can('manageStaff', $this->gym));
    }

    public function test_platform_admin_passes_every_check()
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->assertTrue($admin->can('view', $this->gym));
        $this->assertTrue($admin->can('update', $this->gym));
        $this->assertTrue($admin->can('manageStaff', $this->gym));
    }
}
