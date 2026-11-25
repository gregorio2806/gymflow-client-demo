<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Enums\GymStatus;
use App\Http\Middleware\SetCurrentGym;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class GymSubdomainTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $slug, string $path = '/'): string
    {
        return 'http://'.$slug.'.'.config('tenancy.platform_domain').$path;
    }

    public function test_guest_home_resolves_only_host_gym_and_exposes_no_private_details(): void
    {
        $a = Gym::factory()->create(['name' => 'Public Gym Alpha']);
        $b = Gym::factory()->create(['name' => 'Public Gym Beta']);
        $this->get($this->url($a->slug))->assertOk()->assertSee('Public Gym Alpha')->assertDontSee('Public Gym Beta')->assertDontSee($a->phone)->assertDontSee($a->email);
        $this->assertFalse(app(CurrentGym::class)->check());
        $this->get($this->url($b->slug))->assertOk()->assertSee('Public Gym Beta')->assertDontSee('Public Gym Alpha');
        $this->assertFalse(app(CurrentGym::class)->check());
    }

    public function test_public_host_ignores_authenticated_dashboard_gym_and_preserves_session(): void
    {
        $a = Gym::factory()->create(['name' => 'Gym A']);
        $b = Gym::factory()->create(['name' => 'Gym B']);
        $owner = User::factory()->memberOf($a, GymRole::Owner)->create();
        $this->actingAs($owner)->withSession([SetCurrentGym::SESSION_KEY => $a->id]);
        $this->get($this->url($b->slug))->assertOk()->assertSee('Gym B')->assertDontSee('Gym A')->assertSessionHas(SetCurrentGym::SESSION_KEY, $a->id);
        $this->assertFalse(app(CurrentGym::class)->check());
        $this->get(config('app.url').'/dashboard')->assertOk();
        $this->assertSame($a->id, app(CurrentGym::class)->id());
    }

    public function test_unknown_reserved_nested_and_foreign_hosts_fail_closed(): void
    {
        foreach (['missing', 'www', 'api', 'nested.gym'] as $slug) {
            $this->get($this->url($slug))->assertNotFound();
        }
        $this->get('http://attacker.invalid/')->assertNotFound();
        $this->get('http://attacker.invalid/login')->assertNotFound();
    }

    public function test_gym_hosts_cannot_reach_platform_auth_dashboard_or_mutation_routes(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->memberOf($gym, GymRole::Owner)->create();
        $this->actingAs($owner);
        foreach (['/dashboard', '/members', '/login', '/gym/settings', '/request-access'] as $path) {
            $this->get($this->url($gym->slug, $path))->assertNotFound();
        }
        $this->post($this->url($gym->slug, '/members'), ['name' => 'No access'])->assertNotFound();
        $this->assertDatabaseCount('members', 0);
    }

    public function test_disabled_suspended_and_demo_gyms_are_not_public(): void
    {
        $gym = Gym::factory()->create();
        Feature::for($gym)->deactivate('website');
        $this->get($this->url($gym->slug))->assertNotFound();
        Feature::for($gym)->activate('website');
        $gym->forceFill(['status' => GymStatus::Suspended])->save();
        $this->get($this->url($gym->slug))->assertNotFound();
        $gym->forceFill(['status' => GymStatus::Active, 'demo_expires_at' => now()->addHour()])->save();
        $this->get($this->url($gym->slug))->assertNotFound();
    }

    public function test_inertia_visit_uses_full_page_navigation_and_name_is_escaped(): void
    {
        $gym = Gym::factory()->create(['name' => '<script>alert(1)</script>']);
        $url = $this->url($gym->slug);
        $this->get($url)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->withHeader('X-Inertia', 'true')->get($url)->assertStatus(409)->assertHeader('X-Inertia-Location', rtrim($url, '/'));
    }

    public function test_guests_on_public_hosts_get_404_instead_of_platform_login_redirects(): void
    {
        $gym = Gym::factory()->create();
        $this->get($this->url($gym->slug, '/dashboard'))->assertNotFound();
        $this->get($this->url($gym->slug, '/login'))->assertNotFound();
    }
}
