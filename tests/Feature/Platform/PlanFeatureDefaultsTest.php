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
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanFeatureDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public static function plans(): array
    {
        return [['starter', false], ['pro', true], ['multi_branch', true]];
    }

    #[DataProvider('plans')]
    public function test_plan_defaults_keep_core_features_and_gate_premium_features(string $plan, bool $premium): void
    {
        $gym = Gym::factory()->create();
        $gym->subscription->update(['plan' => $plan]);
        foreach (GymFeature::cases() as $feature) {
            $expected = $feature->dependsOnPlan() ? $premium : ! in_array($feature, [GymFeature::CalorieHelper, GymFeature::MemberAuth], true);
            $this->assertSame($expected, Feature::for($gym)->active($feature->value), $feature->value);
        }
    }

    public function test_cached_defaults_follow_upgrades_and_downgrades(): void
    {
        $gym = Gym::factory()->create();
        foreach (['starter' => false, 'pro' => true, 'multi_branch' => true] as $plan => $enabled) {
            $gym->subscription->update(['plan' => $plan]);
            foreach (GymFeature::cases() as $feature) {
                if ($feature->dependsOnPlan()) {
                    $this->assertSame($enabled, Feature::for($gym)->active($feature->value));
                }
            }
        }
        $gym->subscription->update(['plan' => 'starter']);
        $this->assertFalse(Feature::for($gym)->active('full-reports'));
    }

    public function test_admin_overrides_survive_plan_changes_and_defaults_can_be_restored(): void
    {
        $gym = Gym::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('platform.gyms.features.update', $gym), ['overrides' => ['online-payments' => 'on', 'full-reports' => 'off']])->assertSessionHasNoErrors();
        $gym->refresh();
        foreach (['pro', 'starter', 'multi_branch'] as $plan) {
            $gym->subscription->update(['plan' => $plan]);
            $this->assertTrue(Feature::for($gym)->active('online-payments'));
            $this->assertFalse(Feature::for($gym)->active('full-reports'));
        }
        $this->actingAs($admin)->put(route('platform.gyms.features.update', $gym), ['overrides' => ['online-payments' => 'default', 'full-reports' => 'default']])->assertSessionHasNoErrors();
        $gym->refresh();
        $this->assertTrue(Feature::for($gym)->active('full-reports'));
        $gym->subscription->update(['plan' => 'starter']);
        $this->assertFalse(Feature::for($gym)->active('online-payments'));
        $this->assertFalse(Feature::for($gym)->active('full-reports'));
    }

    public function test_explicit_override_equal_to_default_is_still_persistent_and_audited(): void
    {
        $gym = Gym::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('platform.gyms.features.update', $gym), ['overrides' => ['online-payments' => 'off']])->assertSessionHasNoErrors();
        app(CurrentGym::class)->set($gym);
        $this->assertSame(['online-payments' => false], AuditLog::sole()->changes['after']['overrides']);
        $gym->refresh()->subscription->update(['plan' => 'pro']);
        $this->assertFalse(Feature::for($gym)->active('online-payments'));
    }

    public function test_other_gym_and_legacy_core_overrides_are_unchanged(): void
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        Feature::for($gymA)->deactivate('workouts');
        $this->actingAs($admin)->put(route('platform.gyms.features.update', $gymB), ['overrides' => ['online-payments' => 'on']])->assertSessionHasNoErrors();
        $gymA->subscription->update(['plan' => 'pro']);
        $this->assertFalse(Feature::for($gymA)->active('workouts'));
        $this->assertTrue(Feature::for($gymA)->active('online-payments'));
        $gymA->subscription->update(['plan' => 'starter']);
        $this->assertFalse(Feature::for($gymA)->active('online-payments'));
        $this->assertTrue(Feature::for($gymB)->active('online-payments'));
        $this->assertNull($gymA->refresh()->feature_overrides);
    }

    public function test_premium_middleware_and_shared_ui_follow_the_plan(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'has-gym', 'feature:full-reports'])->get('test-full-reports', fn () => 'full reports');
        $gym = Gym::factory()->create();
        $owner = User::factory()->memberOf($gym, GymRole::Owner)->create();
        $this->actingAs($owner)->get('test-full-reports')->assertNotFound();
        $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('features', fn ($features) => ! collect($features)->contains('full-reports') && collect($features)->contains('reports')));
        $gym->subscription->update(['plan' => 'pro']);
        $this->actingAs($owner)->get('test-full-reports')->assertOk();
        $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('features', fn ($features) => collect($features)->contains('full-reports')));
    }

    public function test_owners_cannot_override_and_invalid_modes_are_rejected(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->memberOf($gym, GymRole::Owner)->create();
        $this->actingAs($owner)->put(route('platform.gyms.features.update', $gym), ['overrides' => ['online-payments' => 'on']])->assertForbidden();
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->put(route('platform.gyms.features.update', $gym), ['overrides' => ['online-payments' => 'maybe']])->assertSessionHasErrors('overrides.online-payments');
        $this->actingAs($admin)->put(route('platform.gyms.features.update', $gym), ['overrides' => ['unknown-feature' => 'on']])->assertSessionHasErrors('overrides');
        $this->assertNull($gym->refresh()->feature_overrides);
    }
}
