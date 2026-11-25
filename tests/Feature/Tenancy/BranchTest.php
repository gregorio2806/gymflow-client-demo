<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_gym_gets_exactly_one_main_branch()
    {
        $gym = Gym::factory()->create(['address' => 'Cubao, QC', 'phone' => '09171234567']);

        $branches = $gym->branches()->get();
        $this->assertCount(1, $branches);
        $this->assertTrue($branches[0]->is_main);
        $this->assertSame('Main branch', $branches[0]->name);
        $this->assertSame('Cubao, QC', $branches[0]->address);
        $this->assertTrue($gym->mainBranch()->is($branches[0]));
    }

    public function test_main_branch_belongs_to_the_new_gym_even_while_another_gym_is_current()
    {
        $current = Gym::factory()->create();
        app(CurrentGym::class)->set($current, GymRole::Owner);

        $newGym = Gym::factory()->create();

        $this->assertSame($newGym->id, $newGym->mainBranch()->gym_id);
        $this->assertCount(1, $current->branches()->get());
        // The request's current gym is restored afterwards.
        $this->assertSame($current->id, app(CurrentGym::class)->id());
        $this->assertSame(GymRole::Owner, app(CurrentGym::class)->role());
    }

    public function test_owner_creating_a_second_gym_gets_its_own_main_branch()
    {
        $first = Gym::factory()->create(['name' => 'First Gym']);
        $owner = User::factory()->memberOf($first, GymRole::Owner)->create();
        $this->grantGymAccess($owner); // a second gym needs its own access from the platform admin

        $this->actingAs($owner)->post(route('gyms.store'), ['name' => 'Second Gym']);

        $second = Gym::where('name', 'Second Gym')->sole();
        $this->assertCount(1, $second->branches()->get());
        $this->assertCount(1, $first->branches()->get());
    }

    public function test_branches_are_scoped_to_the_current_gym()
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();
        app(CurrentGym::class)->set($gymA);
        Branch::create(['name' => 'Second branch']);

        $this->assertSame(2, Branch::count());
        $this->assertTrue(Branch::pluck('gym_id')->every(fn ($id) => $id === $gymA->id));

        app(CurrentGym::class)->set($gymB);
        $this->assertSame(1, Branch::count());
    }

    public function test_deleting_a_gym_deletes_its_branches()
    {
        $gym = Gym::factory()->create();
        $gym->delete();

        $this->assertDatabaseCount('branches', 0);
    }

    public function test_demo_gyms_get_a_main_branch_too()
    {
        $this->post(route('demo.store'));

        $this->assertCount(1, Gym::sole()->branches()->get());
    }
}
