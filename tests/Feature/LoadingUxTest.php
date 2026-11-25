<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class LoadingUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Render like production (built assets), even if `composer run dev` is running.
        Vite::useHotFile(storage_path('framework/testing-no-hot-file'));
    }

    public function test_ssr_is_off_so_dev_matches_shared_hosting()
    {
        $this->assertFalse(config('inertia.ssr.enabled'));
    }

    public function test_app_pages_render_an_empty_root_followed_by_the_splash()
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        // The splash must come right after the empty #app so the CSS sibling rule can hide it.
        $this->assertMatchesRegularExpression('/<div id="app"><\/div>\s*<div class="boot-splash" role="status">/', $html);
        $this->assertStringContainsString('#app:not(:empty) ~ .boot-splash { display: none; }', $html);
    }

    public function test_splash_needs_no_extra_downloads()
    {
        $this->withoutVite();

        $html = $this->get(route('login'))->getContent();
        $splash = substr($html, strpos($html, '<div class="boot-splash"'));

        $this->assertStringNotContainsString('<img', $splash);
        $this->assertStringNotContainsString('src=', $splash);
        $this->assertStringContainsString('<svg', $splash);
    }
}
