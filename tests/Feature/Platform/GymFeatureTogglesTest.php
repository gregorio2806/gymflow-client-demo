<?php

namespace Tests\Feature\Platform;

use App\Enums\GymFeature;
use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class GymFeatureTogglesTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gymA;

    private Gym $gymB;

    private User $admin;

    private User $ownerA;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gymA = Gym::factory()->create(['name' => 'Gym A']);
        $this->gymB = Gym::factory()->create(['name' => 'Gym B']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->ownerA = User::factory()->memberOf($this->gymA, GymRole::Owner)->create();
        $this->ownerB = User::factory()->memberOf($this->gymB, GymRole::Owner)->create();

        // A stand-in for a future module page, guarded like real modules will be.
        Route::middleware(['web', 'auth', 'verified', 'has-gym', 'feature:workouts'])
            ->get('test-workouts', fn () => 'workouts page');
    }

    /**
     * @param  list<string>  $features
     */
    private function saveFeatures(Gym $gym, array $features): TestResponse
    {
        return $this->actingAs($this->admin)->put(route('platform.gyms.features.update', $gym), ['features' => $features]);
    }

    private function allExcept(GymFeature $off): array
    {
        return collect(GymFeature::cases())->reject(fn ($f) => $f === $off)->map->value->values()->all();
    }

    public function test_starter_features_follow_their_defaults()
    {
        foreach (GymFeature::cases() as $feature) {
            $this->assertSame(
                $feature->enabledByDefault(),
                Feature::for($this->gymA)->active($feature->value),
                $feature->value,
            );
        }
    }

    public function test_active_features_are_shared_with_the_ui()
    {
        $this->actingAs($this->ownerA)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('features', fn ($features) => collect($features)->contains('workouts')
                    && ! collect($features)->contains('calorie-helper'))
                ->where('isPlatformAdmin', false));
    }

    public function test_switching_a_feature_off_blocks_its_pages_for_that_gym_only()
    {
        $this->actingAs($this->ownerA)->get('test-workouts')->assertOk();

        $this->saveFeatures($this->gymA, $this->allExcept(GymFeature::Workouts))
            ->assertRedirect(route('platform.gyms.features.edit', $this->gymA));

        $this->actingAs($this->ownerA)->get('test-workouts')->assertNotFound();
        $this->actingAs($this->ownerA)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('features', fn ($f) => ! collect($f)->contains('workouts')));

        $this->actingAs($this->ownerB)->get('test-workouts')->assertOk();
    }

    public function test_switching_a_feature_back_on_restores_access()
    {
        $this->saveFeatures($this->gymA, $this->allExcept(GymFeature::Workouts));
        $this->saveFeatures($this->gymA, array_map(fn ($f) => $f->value, GymFeature::cases()));

        $this->actingAs($this->ownerA)->get('test-workouts')->assertOk();
        $this->assertTrue(Feature::for($this->gymA)->active('calorie-helper'));
    }

    public function test_changes_are_recorded_in_that_gyms_audit_log()
    {
        $this->saveFeatures($this->gymA, $this->allExcept(GymFeature::Workouts));

        app(CurrentGym::class)->set($this->gymA);
        $log = AuditLog::sole();
        $this->assertSame('features.updated', $log->action);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertContains('workouts', $log->changes['before']['features']);
        $this->assertNotContains('workouts', $log->changes['after']['features']);

        app(CurrentGym::class)->set($this->gymB);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_saving_without_changes_writes_no_audit_log()
    {
        $this->saveFeatures($this->gymA, collect(GymFeature::cases())->filter(fn ($f) => $f->enabledByDefault())->map->value->values()->all());

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unknown_features_are_rejected()
    {
        $this->saveFeatures($this->gymA, ['teleportation'])->assertSessionHasErrors('features.0');
    }

    public function test_platform_admin_sees_the_gym_list_and_feature_page()
    {
        $this->actingAs($this->admin)->get(route('platform.gyms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('platform/Gyms')->has('gyms.data', 2));

        $this->actingAs($this->admin)->get(route('platform.gyms.features.edit', $this->gymA))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/GymFeatures')
                ->has('features', count(GymFeature::cases()))
                ->where('features.0.key', 'members')
                ->where('features.0.active', true));
    }

    public function test_gym_owners_and_staff_cannot_use_the_platform_admin_pages()
    {
        $staff = User::factory()->memberOf($this->gymA, GymRole::Staff)->create();

        foreach ([$this->ownerA, $staff] as $user) {
            $this->actingAs($user)->get(route('platform.gyms.index'))->assertForbidden();
            $this->actingAs($user)->get(route('platform.gyms.features.edit', $this->gymA))->assertForbidden();
            $this->actingAs($user)
                ->put(route('platform.gyms.features.update', $this->gymA), ['features' => []])
                ->assertForbidden();
        }

        $this->assertTrue(Feature::for($this->gymA)->active('workouts'));
    }
}
