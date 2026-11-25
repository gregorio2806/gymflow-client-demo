<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamMemberEditTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create(['name' => 'Olive Owner']);
        $this->staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create(['name' => 'Jaun Dela Cruz']);
    }

    private function edit(User $member, array $data): TestResponse
    {
        return $this->actingAs($this->owner)->patch(route('staff.update', $member->id), $data);
    }

    public function test_owner_can_fix_a_name_typo()
    {
        $this->edit($this->staff, ['name' => 'Juan Dela Cruz', 'role' => 'staff'])
            ->assertRedirect(route('staff.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Juan Dela Cruz', $this->staff->refresh()->name);
    }

    public function test_owner_can_switch_staff_to_coach_and_back()
    {
        $this->edit($this->staff, ['name' => $this->staff->name, 'role' => 'coach']);
        $this->assertSame(GymRole::Coach, $this->staff->roleIn($this->gym));

        $this->edit($this->staff, ['name' => $this->staff->name, 'role' => 'staff']);
        $this->assertSame(GymRole::Staff, $this->staff->roleIn($this->gym));
    }

    public function test_changes_are_audit_logged_with_before_and_after()
    {
        $this->edit($this->staff, ['name' => 'Juan Dela Cruz', 'role' => 'coach']);

        app(CurrentGym::class)->set($this->gym);
        $log = AuditLog::sole();
        $this->assertSame('staff.updated', $log->action);
        $this->assertSame(['name' => 'Jaun Dela Cruz', 'role' => 'staff'], $log->changes['before']);
        $this->assertSame(['name' => 'Juan Dela Cruz', 'role' => 'coach'], $log->changes['after']);
    }

    public function test_saving_without_changes_writes_no_audit_log()
    {
        $this->edit($this->staff, ['name' => 'Jaun Dela Cruz', 'role' => 'staff']);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_nobody_can_be_made_an_owner()
    {
        $this->edit($this->staff, ['name' => $this->staff->name, 'role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertSame(GymRole::Staff, $this->staff->roleIn($this->gym));
    }

    public function test_the_owners_role_cannot_be_changed_but_their_name_can()
    {
        $this->edit($this->owner, ['name' => 'Olive Owner', 'role' => 'staff'])
            ->assertSessionHasErrors('role');
        $this->assertSame(GymRole::Owner, $this->owner->roleIn($this->gym));

        $this->edit($this->owner, ['name' => 'Olivia Owner'])->assertSessionHasNoErrors();
        $this->assertSame('Olivia Owner', $this->owner->refresh()->name);
    }

    public function test_people_who_also_work_at_another_gym_cannot_be_renamed()
    {
        $this->staff->gyms()->attach(Gym::factory()->create(), ['role' => GymRole::Coach]);

        $this->edit($this->staff, ['name' => 'Renamed', 'role' => 'coach'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Jaun Dela Cruz', $this->staff->refresh()->name);
    }

    public function test_staff_page_says_who_can_be_renamed()
    {
        $this->staff->gyms()->attach(Gym::factory()->create(), ['role' => GymRole::Coach]);

        $this->actingAs($this->owner)->get(route('staff.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.0.name', 'Jaun Dela Cruz')
                ->where('members.0.canEditName', false)
                ->where('members.1.name', 'Olive Owner')
                ->where('members.1.canEditName', true));
    }

    public function test_staff_and_coaches_cannot_edit_the_team()
    {
        $coach = User::factory()->memberOf($this->gym, GymRole::Coach)->create();

        $this->actingAs($coach)
            ->patch(route('staff.update', $this->staff->id), ['name' => 'Hacked', 'role' => 'coach'])
            ->assertForbidden();

        $this->assertSame('Jaun Dela Cruz', $this->staff->refresh()->name);
    }

    public function test_name_is_required()
    {
        $this->edit($this->staff, ['name' => '', 'role' => 'staff'])->assertSessionHasErrors('name');
    }
}
