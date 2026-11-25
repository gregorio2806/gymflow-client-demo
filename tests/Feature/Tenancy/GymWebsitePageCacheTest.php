<?php

namespace Tests\Feature\Tenancy;

use App\Actions\ManageGymWebsite;
use App\Enums\GymRole;
use App\Enums\GymStatus;
use App\Http\Middleware\SetCurrentGym;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\GymWebsite;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;

class GymWebsitePageCacheTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private GymWebsite $site;

    private User $owner;

    private int $renders = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        $this->gym = Gym::factory()->create(['name' => 'Cache Gym']);
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->site = $this->website($this->gym, 'Published gym A');
        View::composer('gym-site.page', function () {
            $this->renders++;
        });
    }

    private function website(Gym $gym, string $description): GymWebsite
    {
        return app(CurrentGym::class)->runAs($gym, function () use ($description) {
            $site = new GymWebsite;
            $site->forceFill(['published_content' => ['description' => $description, 'plans' => [], 'facilities' => [], 'announcements' => []], 'published_at' => now()->utc()])->save();

            return $site;
        });
    }

    private function url(?Gym $gym = null, string $query = '', string $scheme = 'http', string $port = ''): string
    {
        return $scheme.'://'.($gym ?? $this->gym)->slug.'.'.config('tenancy.platform_domain').$port.'/'.$query;
    }

    private function changeDraft(string $description): void
    {
        app(CurrentGym::class)->runAs($this->gym, function () use ($description) {
            $this->site->refresh()->forceFill(['draft_content' => ['description' => $description, 'plans' => [], 'facilities' => [], 'announcements' => []], 'draft_revision' => ($this->site->draft_revision ?? 0) + 1])->save();
        });
    }

    private function publish(): void
    {
        $this->actingAs($this->owner);
        app(CurrentGym::class)->runAs($this->gym, fn () => app(ManageGymWebsite::class)->publish($this->site->draft_revision));
    }

    public function test_repeat_requests_reuse_only_published_html_and_expire(): void
    {
        $first = $this->get($this->url())->assertOk()->assertSee('Published gym A')->assertHeader('Cache-Control', 'no-cache, private');
        $second = $this->get($this->url())->assertOk();
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(1, $this->renders);
        $this->travel(301)->seconds();
        $this->get($this->url())->assertOk();
        $this->assertSame(2, $this->renders);
    }

    public function test_drafts_do_not_bust_live_cache_but_publish_and_exact_retries_are_safe(): void
    {
        $this->get($this->url())->assertSee('Published gym A');
        $version = $this->site->published_revision;
        $this->changeDraft('New private draft');
        $this->get($this->url())->assertSee('Published gym A')->assertDontSee('New private draft');
        $this->assertSame($version, $this->site->published_revision);
        $this->assertSame(1, $this->renders);
        $this->publish();
        $this->get($this->url())->assertSee('New private draft')->assertDontSee('Published gym A');
        $this->assertSame(2, $this->renders);
        $this->publish();
        $this->get($this->url())->assertSee('New private draft');
        $this->assertSame(2, $this->renders);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_a_late_old_cache_writer_cannot_restore_stale_html_after_publish(): void
    {
        $this->get($this->url())->assertSee('Published gym A');
        $old = Cache::get('website-page:'.$this->gym->id);
        $this->changeDraft('New published content');
        $this->publish();
        $this->get($this->url())->assertSee('New published content');
        // Simulate a slow old request filling the same slot after the new publication.
        Cache::put('website-page:'.$this->gym->id, $old, 300);
        $this->get($this->url())->assertSee('New published content')->assertDontSee('Published gym A');
        $this->assertNotSame($old['version'], Cache::get('website-page:'.$this->gym->id)['version']);
    }

    public function test_tenants_hosts_scheme_and_port_never_share_canonical_html(): void
    {
        $other = Gym::factory()->create();
        $this->website($other, 'Published gym B');
        $this->get($this->url())->assertSee('Published gym A')->assertDontSee('Published gym B');
        $this->get($this->url($other))->assertSee('Published gym B')->assertDontSee('Published gym A');
        $this->get($this->url(null, '', 'https'))->assertSee('href="https://'.$this->gym->slug.'.'.config('tenancy.platform_domain').'"', false);
        $this->get($this->url(null, '', 'http', ':8123'))->assertSee(':8123', false);
        $this->get($this->url())->assertDontSee(':8123', false)->assertDontSee('href="https://', false);
        $this->assertSame(4, $this->renders);
    }

    public function test_feature_status_unpublication_and_deletion_are_checked_before_cache_hits(): void
    {
        $this->get($this->url())->assertOk();
        Feature::for($this->gym)->deactivate('website');
        $this->get($this->url())->assertNotFound();
        Feature::for($this->gym)->activate('website');
        $this->gym->forceFill(['status' => GymStatus::Suspended])->save();
        $this->get($this->url())->assertNotFound();
        $this->gym->forceFill(['status' => GymStatus::Active])->save();
        app(CurrentGym::class)->runAs($this->gym, fn () => $this->site->forceFill(['published_at' => null])->save());
        $this->get($this->url())->assertSee('not published yet')->assertDontSee('Published gym A');
        $this->gym->delete();
        $this->get($this->url())->assertNotFound();
        $this->assertSame(1, $this->renders);
    }

    public function test_preview_never_enters_public_cache_and_visitors_receive_their_own_response_headers(): void
    {
        $this->get($this->url())->assertOk();
        $this->changeDraft('Private preview');
        $this->actingAs($this->owner)->withSession([SetCurrentGym::SESSION_KEY => $this->gym->id]);
        $previewUrl = rtrim(config('app.url'), '/').route('website.preview', [], false);
        $this->get($previewUrl)->assertOk()->assertSee('Private preview')->assertHeader('Cache-Control', 'no-store, private');
        $this->get($previewUrl)->assertOk();
        $this->assertSame(3, $this->renders);
        $this->get($this->url())->assertSee('Published gym A')->assertDontSee('Private preview')->assertDontSee($this->owner->email);
        $this->assertSame(3, $this->renders);
        $this->get($this->url(), ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location');
    }

    public function test_tracking_queries_share_one_entry_but_pagination_bypasses_cache(): void
    {
        $this->get($this->url(null, '?utm_source=private-tracking'))->assertOk()->assertDontSee('private-tracking');
        $this->get($this->url(null, '?plans_page=1&facilities_page=abc'))->assertOk();
        $this->get($this->url())->assertOk();
        $this->assertSame(1, $this->renders);
        $this->get($this->url(null, '?plans_page=2'))->assertOk();
        $this->get($this->url(null, '?plans_page=2'))->assertOk();
        $this->assertSame(3, $this->renders);
    }

    public function test_alternate_origins_and_disabled_cache_do_not_store_extra_variants(): void
    {
        $this->get($this->url())->assertOk();
        foreach ([8123, 8124] as $port) {
            $this->get($this->url(null, '', 'http', ':'.$port))->assertOk();
            $this->get($this->url(null, '', 'http', ':'.$port))->assertOk();
        }
        $this->assertSame(5, $this->renders);
        $this->get($this->url())->assertOk();
        $this->assertSame(5, $this->renders);
        config(['website.cache_ttl_seconds' => 0]);
        $this->get($this->url())->assertOk();
        $this->get($this->url())->assertOk();
        $this->assertSame(7, $this->renders);
    }

    public function test_gym_rename_and_direct_published_updates_make_old_html_unreachable(): void
    {
        $this->get($this->url())->assertSee('Cache Gym');
        $this->gym->update(['name' => 'Renamed Gym']);
        $this->get($this->url())->assertSee('Renamed Gym')->assertDontSee('Cache Gym');
        app(CurrentGym::class)->runAs($this->gym, fn () => $this->site->forceFill(['published_content' => ['description' => 'Changed published snapshot']])->save());
        $this->get($this->url())->assertSee('Changed published snapshot')->assertDontSee('Published gym A');
        $this->assertSame(3, $this->renders);
    }

    public function test_failed_publish_rolls_back_version_and_keeps_existing_cached_page(): void
    {
        $this->get($this->url())->assertSee('Published gym A');
        $this->changeDraft('Failed publication');
        $version = $this->site->published_revision;
        AuditLog::creating(fn () => throw new RuntimeException('Audit failed'));
        try {
            $this->publish();
            $this->fail('Expected failed audit');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit failed', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        app(CurrentGym::class)->runAs($this->gym, fn () => $this->assertSame($version, $this->site->refresh()->published_revision));
        $this->get($this->url())->assertSee('Published gym A')->assertDontSee('Failed publication');
        $this->assertSame(1, $this->renders);
    }

    public function test_cache_read_and_write_failures_fall_back_to_published_rendering(): void
    {
        Cache::shouldReceive('get')->once()->andThrow(new RuntimeException('Cache unavailable'));
        $this->get($this->url())->assertOk()->assertSee('Published gym A');
        Cache::shouldReceive('get')->once()->andReturn(null);
        Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('Cache unavailable'));
        $this->get($this->url())->assertOk()->assertSee('Published gym A');
        $this->assertSame(2, $this->renders);
    }
}
