<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_a_role_in_their_gym()
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->memberOf($gym, GymRole::Owner)->create();

        $this->assertSame(GymRole::Owner, $owner->roleIn($gym));
        $this->assertTrue($gym->users->contains($owner));
    }

    public function test_user_has_no_role_in_another_gym()
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();
        $staff = User::factory()->memberOf($gymA)->create();

        $this->assertNull($staff->roleIn($gymB));
    }

    public function test_user_can_belong_to_several_gyms_with_different_roles()
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();
        $user = User::factory()->memberOf($gymA, GymRole::Owner)->memberOf($gymB, GymRole::Coach)->create();

        $this->assertCount(2, $user->gyms);
        $this->assertSame(GymRole::Owner, $user->roleIn($gymA));
        $this->assertSame(GymRole::Coach, $user->roleIn($gymB));
    }

    public function test_user_cannot_be_attached_to_the_same_gym_twice()
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->memberOf($gym)->create();

        $this->expectException(QueryException::class);

        $user->gyms()->attach($gym, ['role' => GymRole::Coach]);
    }

    public function test_deleting_a_gym_removes_its_memberships()
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->memberOf($gym)->create();

        $gym->delete();

        $this->assertCount(0, $user->gyms()->get());
        $this->assertDatabaseCount('gym_user', 0);
    }
}
