<?php

namespace Tests\Feature\Tenancy;

use App\Models\Gym;
use App\Models\GymWebsite;
use App\Tenancy\CurrentGym;
use Database\Seeders\IronHouseWebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;

class GymWebsiteTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create(['name' => 'Iron House Fitness', 'slug' => 'iron-house-fitness']);
        $this->seed(IronHouseWebsiteSeeder::class);
    }

    private function url(string $path = ''): string
    {
        return 'http://'.$this->gym->slug.'.'.config('tenancy.platform_domain').'/'.$path;
    }

    public function test_check_membership_links_target_this_gym_and_follow_portal_feature_even_when_cached(): void
    {
        config(['app.url' => 'http://localhost']);
        $url = rtrim($this->url(), '/').'/portal/check-membership';
        $response = $this->get($this->url())->assertSee('Check membership')->assertDontSee('Member login')->assertSee('href="'.$url.'"', false);
        $this->assertSame(2, substr_count($response->getContent(), '>Check membership</a>'));
        Feature::for($this->gym)->deactivate('member-portal');
        $this->get($this->url())->assertDontSee('Check membership');
        Feature::for($this->gym)->activate('member-portal');
        $this->get($this->url())->assertSee('Check membership');
    }

    public function test_public_home_renders_every_section_navigation_seo_and_explicit_published_content(): void
    {
        $response = $this->get($this->url())->assertOk()->assertSee('name="description"', false)->assertSee('rel="canonical"', false)->assertSee('property="og:title"', false)->assertSee('Skip to content')->assertDontSee($this->gym->phone)->assertDontSee('member@ironhouse.test');
        foreach (['home', 'about', 'plans', 'facilities', 'announcements', 'contact'] as $section) {
            $response->assertSee('id="'.$section.'"', false)->assertSee('href="#'.$section.'"', false);
        }
        foreach (['Make space for your next workout.', 'fictional development gym', 'Monthly access', 'Strength area', 'Welcome to the sample website', 'Opening hours', '₱1,200.00', '₱150.00'] as $text) {
            $response->assertSee($text);
        }
        $this->assertSame(1, substr_count($response->getContent(), '<h1 '));
    }

    public function test_legacy_page_links_redirect_to_matching_sections_and_preserve_pagination(): void
    {
        foreach (['about', 'plans', 'facilities', 'announcements', 'contact'] as $section) {
            $this->get($this->url($section))->assertStatus(301)->assertRedirect(rtrim($this->url(), '/').'#'.$section);
        }
        $this->get($this->url('facilities?page=2'))->assertRedirect(rtrim($this->url(), '/').'?facilities_page=2#facilities');
    }

    public function test_gym_b_cannot_see_gym_a_published_snapshot_and_unpublished_pages_are_not_indexed(): void
    {
        $other = Gym::factory()->create(['name' => 'Other Gym']);
        $base = 'http://'.$other->slug.'.'.config('tenancy.platform_domain');
        $this->get($base)->assertOk()->assertSee('not published yet')->assertSee('noindex')->assertDontSee('Monthly access');
        foreach (['about', 'plans', 'facilities', 'announcements', 'contact', 'sitemap.xml'] as $page) {
            $this->get($base.'/'.$page)->assertNotFound();
        }
        app(CurrentGym::class)->set($other);
        $this->assertSame(0, GymWebsite::count());
    }

    public function test_lists_paginate_and_strings_are_escaped(): void
    {
        app(CurrentGym::class)->runAs($this->gym, function () {
            $site = GymWebsite::sole();
            $data = $site->published_content;
            $data['about'] = '<script>alert(1)</script>';
            $data['facilities'] = array_map(fn ($index) => ['name' => 'Facility '.$index, 'description' => '<img src=x onerror=alert(1)>'], range(1, 13));
            $site->forceFill(['published_content' => $data])->save();
        });
        $this->get($this->url())->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get($this->url())->assertSee('Facility 12')->assertDontSee('Facility 13')->assertSee('Next')->assertDontSee('<img src=x', false)->assertSee('facilities_page=2#facilities', false);
        $this->get($this->url('?facilities_page=2'))->assertSee('Facility 13')->assertDontSee('Facility 12')->assertSee('Previous')->assertSee('Monthly access')->assertSee('Opening hours');
    }

    public function test_sitemap_contains_only_this_gym_and_website_feature_blocks_all_pages(): void
    {
        $response = $this->get($this->url('sitemap.xml'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee(rtrim($this->url(), '/'), false)->assertDontSee('/plans')->assertDontSee('/dashboard')->assertDontSee('/login');
        $this->assertSame(1, substr_count($response->getContent(), '<loc>'));
        Feature::for($this->gym)->deactivate('website');
        $this->get($this->url('plans'))->assertNotFound();
        $this->get($this->url('sitemap.xml'))->assertNotFound();
    }

    public function test_section_pagination_is_independent_and_invalid_page_values_are_bounded(): void
    {
        app(CurrentGym::class)->runAs($this->gym, function () {
            $site = GymWebsite::sole();
            $data = $site->published_content;
            $data['facilities'] = array_map(fn ($index) => ['name' => 'Facility '.$index], range(1, 13));
            $data['plans'] = array_map(fn ($index) => ['name' => 'Sample plan '.$index, 'price_centavos' => 100], range(1, 13));
            $site->forceFill(['published_content' => $data])->save();
        });
        $response = $this->get($this->url('?facilities_page=2&plans_page=1'))->assertOk()->assertSee('Facility 13')->assertSee('Sample plan 12')->assertDontSee('Sample plan 13');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $href = (new \DOMXPath($document))->query('//nav[@aria-label="Membership plans pagination"]/a')->item(0)->getAttribute('href');
        parse_str(parse_url($href, PHP_URL_QUERY), $parameters);
        $this->assertSame('2', $parameters['plans_page']);
        $this->assertSame('2', $parameters['facilities_page']);
        $this->assertSame('plans', parse_url($href, PHP_URL_FRAGMENT));
        $this->get($this->url('?facilities_page=2&plans_page=2'))->assertOk()->assertSee('Facility 13')->assertSee('Sample plan 13')->assertDontSee('Sample plan 12');
        foreach (['-1', '999999999999999999999999', 'abc', '%3Cscript%3E'] as $value) {
            $this->get($this->url('?facilities_page='.$value))->assertOk()->assertSee('Facility 12')->assertDontSee('Facility 13');
        }
        $this->get($this->url('?facilities_page[]=2'))->assertOk()->assertSee('Facility 12');
    }

    public function test_local_sample_seeder_preserves_existing_content_and_is_blocked_outside_local_testing(): void
    {
        app(CurrentGym::class)->runAs($this->gym, function () {
            $site = GymWebsite::sole();
            $data = $site->published_content;
            $data['about'] = 'Keep my published content';
            $site->forceFill(['published_content' => $data])->save();
        });
        $this->seed(IronHouseWebsiteSeeder::class);
        $this->get($this->url())->assertSee('Keep my published content');
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        (new IronHouseWebsiteSeeder)->run();
    }
}
