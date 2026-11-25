<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GymSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private array $valid = [
        'name' => 'DM Fitness Cubao',
        'email' => 'hello@dmfitness.test',
        'phone' => '09171234567',
        'address' => 'Cubao, Quezon City',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create(['name' => 'DM Fitness']);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
    }

    public function test_owner_sees_the_settings_page_with_only_needed_fields()
    {
        $this->actingAs($this->owner)
            ->get(route('gym.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/gym/Settings')
                ->where('gym.name', 'DM Fitness')
                ->missing('gym.id')
                ->missing('gym.status')
                ->where('gym.timezone', 'Asia/Manila')
                ->missing('timezones'));
    }

    public function test_owner_can_update_settings_and_it_is_audit_logged()
    {
        $this->actingAs($this->owner)
            ->patch(route('gym.settings.update'), $this->valid)
            ->assertRedirect(route('gym.settings.edit'));

        $this->assertSame('DM Fitness Cubao', $this->gym->refresh()->name);

        app(CurrentGym::class)->set($this->gym);
        $log = AuditLog::sole();
        $this->assertSame('gym.updated', $log->action);
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame('DM Fitness', $log->changes['before']['name']);
    }

    public function test_saving_without_changes_writes_no_audit_log()
    {
        $this->actingAs($this->owner)->patch(route('gym.settings.update'), [
            'name' => 'DM Fitness',
            'email' => $this->gym->email,
            'phone' => $this->gym->phone,
            'address' => $this->gym->address,
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_input_is_rejected()
    {
        $this->actingAs($this->owner)
            ->patch(route('gym.settings.update'), ['name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_staff_and_coaches_cannot_view_or_update_settings()
    {
        foreach ([GymRole::Staff, GymRole::Coach] as $role) {
            $user = User::factory()->memberOf($this->gym, $role)->create();

            $this->actingAs($user)->get(route('gym.settings.edit'))->assertForbidden();
            $this->actingAs($user)->patch(route('gym.settings.update'), $this->valid)->assertForbidden();
        }

        $this->assertSame('DM Fitness', $this->gym->refresh()->name);
    }

    public function test_owner_of_another_gym_only_changes_their_own_gym()
    {
        $otherGym = Gym::factory()->create(['name' => 'Flex Zone']);
        $otherOwner = User::factory()->memberOf($otherGym, GymRole::Owner)->create();

        $this->actingAs($otherOwner)->patch(route('gym.settings.update'), $this->valid);

        $this->assertSame('DM Fitness', $this->gym->refresh()->name);
        $this->assertSame('DM Fitness Cubao', $otherGym->refresh()->name);
    }

    public function test_timezone_is_fixed_to_manila_and_cannot_be_changed()
    {
        $this->actingAs($this->owner)
            ->patch(route('gym.settings.update'), [...$this->valid, 'timezone' => 'Asia/Tokyo'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Asia/Manila', $this->gym->refresh()->timezone);
    }

    public function test_status_and_slug_cannot_be_changed_through_settings()
    {
        $slug = $this->gym->slug;

        $this->actingAs($this->owner)->patch(route('gym.settings.update'), [
            ...$this->valid, 'status' => 'suspended', 'slug' => 'hijacked',
        ]);

        $this->gym->refresh();
        $this->assertSame($slug, $this->gym->slug);
        $this->assertSame('active', $this->gym->status->value);
    }

    public function test_owners_see_the_gym_settings_link_and_staff_do_not()
    {
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('currentGym.role', 'owner'));
        $this->actingAs($staff)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('currentGym.role', 'staff'));
    }
}
