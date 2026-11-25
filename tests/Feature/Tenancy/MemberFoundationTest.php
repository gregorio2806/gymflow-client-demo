<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class MemberFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_default_to_the_current_gym_and_main_branch(): void
    {
        $gym = Gym::factory()->create();
        app(CurrentGym::class)->set($gym);
        $member = Member::create(['name' => 'Alex Santos', 'mobile' => '09000000001']);

        $this->assertSame($gym->id, $member->gym_id);
        $this->assertSame($gym->mainBranch()->id, $member->home_branch_id);
        $this->assertTrue($member->is_active);
        $this->assertNull($member->email);
        $this->assertNull($member->privacy_consented_at);
        $this->assertTrue($member->homeBranch->is($gym->mainBranch()));
    }

    public function test_members_are_isolated_between_gyms_and_fail_closed_without_context(): void
    {
        $a = Gym::factory()->create();
        $b = Gym::factory()->create();
        $context = app(CurrentGym::class);
        $memberA = $context->runAs($a, fn () => Member::factory()->create());
        $memberB = $context->runAs($b, fn () => Member::factory()->create());
        $context->set($b);

        $this->assertNull(Member::find($memberA->id));
        $this->assertSame(0, Member::whereKey($memberA->id)->update(['name' => 'Changed']));
        $this->assertSame([$memberB->id], $b->members()->pluck('id')->all());
        $this->assertSame(0, $a->members()->count());
        $context->forget();
        $this->assertSame(0, Member::count());
    }

    public function test_members_cannot_be_moved_to_another_gym(): void
    {
        $a = Gym::factory()->create();
        $b = Gym::factory()->create();
        app(CurrentGym::class)->set($a);
        $member = Member::factory()->create();
        $member->gym_id = $b->id;

        $this->expectException(LogicException::class);
        $member->save();
    }

    public function test_member_home_branches_cannot_reference_another_gym_on_create(): void
    {
        $a = Gym::factory()->create();
        $b = Gym::factory()->create();
        app(CurrentGym::class)->set($a);

        $this->expectException(LogicException::class);
        Member::factory()->create(['home_branch_id' => $b->mainBranch()->id]);
    }

    public function test_member_home_branches_cannot_reference_another_gym_on_update(): void
    {
        $a = Gym::factory()->create();
        $b = Gym::factory()->create();
        app(CurrentGym::class)->set($a);
        $member = Member::factory()->create();

        $this->expectException(LogicException::class);
        $member->update(['home_branch_id' => $b->mainBranch()->id]);
    }

    public function test_branch_managers_only_query_members_in_their_home_branch(): void
    {
        $gym = Gym::factory()->create();
        $context = app(CurrentGym::class);
        $context->set($gym);
        $mainMember = Member::factory()->create();
        $second = Branch::factory()->create();
        $secondMember = Member::factory()->create(['home_branch_id' => $second->id]);
        $context->set($gym, GymRole::BranchManager, $second->id);

        $this->assertSame([$secondMember->id], Member::pluck('id')->all());
        $this->assertNull(Member::find($mainMember->id));
        $this->assertSame(0, Member::whereKey($mainMember->id)->update(['name' => 'Changed']));
        $context->set($gym, GymRole::BranchManager);
        $this->assertSame(0, Member::count());
    }

    public function test_activation_and_consent_are_not_mass_assignable(): void
    {
        $member = new Member(['gym_id' => 99, 'is_active' => false, 'privacy_consented_at' => now(), 'privacy_recorded_by' => 99]);
        $this->assertNull($member->gym_id);
        $this->assertTrue($member->is_active);
        $this->assertNull($member->privacy_consented_at);
        $this->assertNull($member->privacy_recorded_by);
    }

    public function test_inactive_members_are_preserved_and_can_share_contact_details(): void
    {
        $gym = Gym::factory()->create();
        app(CurrentGym::class)->set($gym);
        Member::factory()->create(['mobile' => '09000000001']);
        $inactive = Member::factory()->inactive()->create(['mobile' => '09000000001']);
        $this->assertFalse($inactive->is_active);
        $this->assertSame(2, $gym->members()->count());
    }

    public function test_deleting_a_gym_removes_only_its_members_even_with_another_gym_current(): void
    {
        $a = Gym::factory()->create();
        $b = Gym::factory()->create();
        $context = app(CurrentGym::class);
        $context->runAs($a, fn () => Member::factory()->create());
        $memberB = $context->runAs($b, fn () => Member::factory()->create());
        $context->set($b);
        $a->delete();

        $this->assertSame(0, Member::withoutGlobalScopes()->where('gym_id', $a->id)->count());
        $this->assertModelExists($memberB);
        $this->assertSame($b->id, $context->id());
    }
}
