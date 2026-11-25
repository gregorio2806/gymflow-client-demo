<?php

namespace Tests\Feature\Security;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaunchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_files_cannot_be_retrieved_over_http(): void
    {
        foreach (['/.env', '/.git/config', '/storage/logs/laravel.log', '/vendor/autoload.php'] as $url) {
            $response = $this->get($url);
            $this->assertSame(404, $response->status(), $url.' '.substr(strip_tags($response->getContent()), 0, 180));
        }
    }

    public function test_headers_cover_public_pages_and_keep_local_camera_available(): void
    {
        $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('/privacy')->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_hsts_is_sent_only_for_production_https(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => false]);
        $this->get('https://'.config('tenancy.platform_domain').'/privacy')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('http://'.config('tenancy.platform_domain').'/privacy')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_production_error_hides_exception_details(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => false]);
        Route::get('/launch-error-test', fn () => throw new \RuntimeException('SECRET-ERROR-MARKER'));
        $this->get('/launch-error-test')->assertServerError()->assertDontSee('SECRET-ERROR-MARKER')->assertDontSee('LaunchSecurityTest.php');
    }

    public function test_dashboard_shared_user_fields_are_explicit(): void
    {
        $user = User::factory()->memberOf(Gym::factory()->create(), GymRole::Owner)->create();
        $user->forceFill(['is_platform_admin' => true, 'terms_version' => 'private-version', 'terms_accepted_at' => now()])->save();
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('auth.user.id', $user->id)->missing('auth.user.password')->missing('auth.user.is_platform_admin')->missing('auth.user.is_demo')->missing('auth.user.terms_version')->missing('auth.user.terms_accepted_at')->missing('auth.user.two_factor_secret'));
    }

    public function test_portal_member_authentication_does_not_grant_dashboard_access(): void
    {
        $gym = Gym::factory()->create();
        $member = app(CurrentGym::class)->runAs($gym, fn () => Member::factory()->create());
        $this->actingAs($member, 'member');
        foreach (['members', 'payments'] as $url) {
            $this->get(config('app.url').'/'.$url)->assertRedirect(route('login'));
            $this->get('http://'.$gym->slug.'.'.config('tenancy.platform_domain').'/'.$url)->assertNotFound();
        }
    }
}
