<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_with_seo_tags()
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('Run your gym,');
    }

    public function test_landing_page_has_one_h1_and_a_skip_link()
    {
        $html = $this->get(route('home'))->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('href="#main"', $html);
    }

    public function test_landing_page_loads_no_javascript_or_external_resources()
    {
        // Render like production (built assets), even if `composer run dev` is running.
        Vite::useHotFile(storage_path('framework/testing-no-hot-file'));

        $html = $this->get(route('home'))->getContent();

        // No downloaded scripts; inline enhancements must stay tiny (the page works without them).
        $this->assertStringNotContainsString('<script src', $html);
        preg_match_all('/<script>(.*?)<\/script>/s', $html, $inline);
        $this->assertLessThan(500, strlen(implode('', $inline[1])), 'Inline scripts on the landing page must stay tiny.');
        $this->assertDoesNotMatchRegularExpression('/(src|href)="https?:\/\/(?!'.preg_quote(parse_url(url('/'), PHP_URL_HOST), '/').')/', $html);
    }

    public function test_vue_app_links_to_the_landing_page_do_a_full_page_load()
    {
        // Without this, Inertia shows the Blade page inside a modal.
        $this->get(route('home'), ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('home'));
    }

    public function test_logging_out_lands_on_the_landing_page_as_a_full_page()
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'), [], ['X-Inertia' => 'true'])->assertRedirect(route('home'));

        $this->get(route('home'), ['X-Inertia' => 'true'])->assertStatus(409);
        $this->get(route('home'))->assertOk()->assertSee('Run your gym,');
    }

    public function test_mobile_has_a_burger_menu_with_the_navigation_and_actions()
    {
        $html = $this->get(route('home'))->getContent();

        $menu = substr($html, strpos($html, '<details data-mobile-menu'));
        $menu = substr($menu, 0, strpos($menu, '</details>'));

        $this->assertStringContainsString('<span class="sr-only">Menu</span>', $menu);
        $this->assertStringContainsString('href="#features"', $menu);
        $this->assertStringContainsString(route('login'), $menu);
    }

    public function test_guests_see_log_in_try_demo_and_request_access()
    {
        $this->get(route('home'))
            ->assertSee(route('login'))
            ->assertSee('Try demo')
            ->assertSee('Request access')
            ->assertSee(route('access-requests.create'), false)
            ->assertDontSee('/register')
            ->assertDontSee('Go to dashboard');
    }

    public function test_public_sign_up_is_closed()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password'])->assertNotFound();
    }

    public function test_logged_in_users_see_a_dashboard_link()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertSee('Go to dashboard')
            ->assertSee(route('dashboard'));
    }
}
