<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Http\Middleware\SetCurrentGym;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\GymWebsite;
use App\Models\User;
use App\Support\WebsiteRichText;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;

class GymWebsiteEditorTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->actingAs($this->owner)->withSession([SetCurrentGym::SESSION_KEY => $this->gym->id]);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function data(array $override = []): array
    {
        return array_replace([
            'revision' => 0, 'description' => 'Private fictional draft', 'about' => '<p>Hello <strong>gym</strong></p>',
            'contact' => ['phone' => '09171234567', 'email' => 'gym@example.invalid', 'address' => 'Fictional address', 'hours' => 'Sample hours'],
            'plans' => [['name' => 'Sample plan', 'price_php' => '1234.05', 'duration' => '1 month', 'description' => 'Example']],
            'facilities' => [], 'announcements' => [], 'image_alt' => '',
        ], $override);
    }

    private function site(): GymWebsite
    {
        return app(CurrentGym::class)->runAs($this->gym, fn () => GymWebsite::sole());
    }

    private function central(string $name, array $parameters = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $parameters, false);
    }

    private function publicUrl(string $path = ''): string
    {
        return 'http://'.$this->gym->slug.'.'.config('tenancy.platform_domain').'/'.$path;
    }

    public function test_website_name_is_editable_and_published_independently_of_gym_settings(): void
    {
        $originalName = $this->gym->name;
        $originalSlug = $this->gym->slug;
        $this->get($this->central('website.edit'))->assertInertia(fn (Assert $page) => $page->where('draft.gym_name', $originalName));
        $this->post($this->central('website.update'), $this->data())->assertRedirect();
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->get($this->publicUrl())->assertSee($originalName);

        $name = 'Website Gym & Fitness';
        $this->post($this->central('website.update'), $this->data(['revision' => 1, 'gym_name' => $name]))->assertRedirect();
        $this->get($this->central('website.edit'))->assertInertia(fn (Assert $page) => $page->where('draft.gym_name', $name));
        $this->get($this->central('website.preview'))->assertSee($name);
        $this->get($this->publicUrl())->assertDontSee($name);
        $this->post($this->central('website.publish'), ['revision' => 2])->assertRedirect();
        $this->get($this->publicUrl())->assertSee($name);
        $this->assertSame($originalName, $this->gym->fresh()->name);
        $this->assertSame($originalSlug, $this->gym->fresh()->slug);

        // Older clients omitting the name must preserve the customized value.
        $this->post($this->central('website.update'), $this->data(['revision' => 2]))->assertRedirect();
        $this->assertSame($name, $this->site()->draft_content['gym_name']);
    }

    public function test_website_name_requires_bounded_text_and_is_escaped_in_preview(): void
    {
        foreach (['', str_repeat('x', 256)] as $name) {
            $this->post($this->central('website.update'), $this->data(['gym_name' => $name]))->assertSessionHasErrors('gym_name');
        }
        $name = '<script>alert(1)</script>';
        $this->post($this->central('website.update'), $this->data(['gym_name' => $name]))->assertRedirect();
        $this->get($this->central('website.preview'))->assertSee($name)->assertDontSee($name, false);
    }

    public function test_save_preview_publish_keeps_draft_private_and_records_atomic_audit(): void
    {
        $this->post($this->central('website.update'), $this->data(['gym_id' => 999, 'published_at' => now()]))->assertRedirect($this->central('website.edit'));
        $this->assertSame(123405, $this->site()->draft_content['plans'][0]['price_centavos']);
        $this->assertNull($this->site()->published_content);
        $this->get($this->publicUrl())->assertSee('not published yet')->assertDontSee('Private fictional draft');
        $this->get($this->central('website.preview', ['section' => 'about']))->assertOk()->assertSee('<strong>gym</strong>', false)->assertSee('Draft preview')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get($this->central('website.preview', ['embedded' => 1]))->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('Return to website editor');
        $this->get($this->central('website.edit'))->assertHeader('X-Frame-Options', 'DENY');
        // Legacy review session data does not gate publication.
        $this->withSession(['website_review' => []]);
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->get($this->publicUrl())->assertSee('Private fictional draft');
        $this->assertDatabaseHas('audit_logs', ['gym_id' => $this->gym->id, 'user_id' => $this->owner->id, 'action' => 'website.published']);
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('audit_logs', 2);
        $this->post($this->central('website.update'), $this->data(['revision' => 1, 'description' => 'Next private draft']))->assertRedirect();
        $this->get($this->publicUrl())->assertDontSee('Next private draft');
        $this->post($this->central('website.publish'), ['revision' => 2])->assertRedirect();
        $this->get($this->publicUrl())->assertSee('Next private draft');
    }

    public function test_stale_save_and_publish_cannot_overwrite_newer_edits(): void
    {
        $this->post($this->central('website.update'), $this->data())->assertRedirect();
        $this->get($this->central('website.preview'))->assertOk();
        $this->post($this->central('website.update'), $this->data(['revision' => 1, 'description' => 'Newer draft']))->assertRedirect();
        $this->post($this->central('website.update'), $this->data())->assertSessionHasErrors('revision');
        $this->post($this->central('website.publish'), ['revision' => 1])->assertSessionHasErrors('revision');
        $this->assertSame('Newer draft', $this->site()->draft_content['description']);
        $this->get($this->central('website.preview', ['section' => 'missing']))->assertNotFound();
    }

    public function test_plans_image_draft_publication_removal_and_cards_are_independent(): void
    {
        $this->post($this->central('website.update'), $this->data())->assertSessionHasNoErrors();
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->post($this->central('website.update'), $this->data([
            'revision' => 1, 'plans_mode' => 'image', 'plans_image' => UploadedFile::fake()->image('prices.png', 3000, 1000),
            'plans_alt' => 'Monthly: PHP 1,200 for 30 days.',
            // Hidden unfinished cards must not block the image workflow or erase saved cards.
            'plans' => [['name' => '', 'price_php' => '', 'duration' => '']],
        ]))->assertSessionHasNoErrors();
        $site = $this->site();
        $path = $site->draft_content['plans_path'];
        $this->assertSame('Sample plan', $site->draft_content['plans'][0]['name']);
        $this->assertSame(123405, $site->draft_content['plans'][0]['price_centavos']);
        $this->assertSame(2400, getimagesize(Storage::disk('local')->path($path))[0]);
        $this->get($this->central('website.image', ['asset' => 'plans']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($this->central('website.preview'))->assertSee('Monthly: PHP 1,200 for 30 days.')->assertSee('View full-size plans image')->assertDontSee('Sample plan');
        $this->get($this->publicUrl())->assertSee('Sample plan')->assertDontSee('View full-size plans image');
        $this->get($this->publicUrl('website-image?asset=plans'))->assertNotFound();
        $this->post($this->central('website.publish'), ['revision' => 2])->assertRedirect();
        $this->get($this->publicUrl())->assertSee('View full-size plans image')->assertDontSee('Sample plan');
        $this->get(route('gym-site.image', ['gym' => $this->gym->slug, 'asset' => 'plans']))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $foreign = Gym::factory()->create();
        $this->get('http://'.$foreign->slug.'.'.config('tenancy.platform_domain').'/website-image?asset=plans')->assertNotFound();

        $this->post($this->central('website.update'), $this->data(['revision' => 2, 'plans_mode' => 'cards', 'remove_plans_image' => true]))->assertSessionHasNoErrors();
        $this->get($this->central('website.preview'))->assertSee('Sample plan')->assertDontSee('View full-size plans image');
        Storage::disk('local')->assertExists($path);
        $this->post($this->central('website.publish'), ['revision' => 3])->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        $this->get($this->publicUrl())->assertSee('Sample plan')->assertDontSee('View full-size plans image');
    }

    public function test_plans_image_requires_valid_upload_and_accessible_description(): void
    {
        $this->post($this->central('website.update'), $this->data(['plans_mode' => 'image']))->assertSessionHasErrors('plans_image');
        $this->post($this->central('website.update'), $this->data(['plans_mode' => 'image', 'plans_image' => UploadedFile::fake()->image('prices.png')]))->assertSessionHasErrors('plans_alt');
        $this->post($this->central('website.update'), $this->data(['plans_mode' => 'image', 'plans_image' => UploadedFile::fake()->create('prices.svg', 10, 'image/svg+xml'), 'plans_alt' => 'Sample plans']))->assertSessionHasErrors('plans_image');
        $this->post($this->central('website.update'), $this->data(['plans_mode' => 'unknown']))->assertSessionHasErrors('plans_mode');
        $this->assertDatabaseCount('gym_websites', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('websites'));
    }

    public function test_saved_preview_contains_all_sections_with_anchor_navigation_and_one_canonical_url(): void
    {
        $this->post($this->central('website.update'), $this->data())->assertRedirect();
        $response = $this->get($this->central('website.preview'))->assertOk()->assertSee('Sample plan')->assertSee('Fictional address')->assertSee('<strong>gym</strong>', false);
        foreach (['home', 'about', 'plans', 'facilities', 'announcements', 'contact'] as $section) {
            $response->assertSee('id="'.$section.'"', false)->assertSee('href="#'.$section.'"', false);
        }
        $response->assertSee('rel="canonical" href="'.route('gym-site.home', ['gym' => $this->gym->slug]).'"', false)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_saved_draft_can_publish_without_preview_even_after_thirty_minutes(): void
    {
        $this->post($this->central('website.update'), $this->data())->assertRedirect();
        $this->travel(31)->minutes();
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->get($this->publicUrl())->assertSee('Private fictional draft');
        $this->assertNotNull($this->site()->published_at);
    }

    public function test_owner_only_feature_gates_every_endpoint(): void
    {
        foreach ([GymRole::Staff, GymRole::Coach, GymRole::BranchManager] as $role) {
            $user = User::factory()->memberOf($this->gym, $role)->create();
            $this->actingAs($user);
            foreach (['edit', 'preview', 'image'] as $endpoint) {
                $this->get($this->central('website.'.$endpoint))->assertForbidden();
            }
            $this->post($this->central('website.update'), $this->data())->assertForbidden();
            $this->post($this->central('website.publish'), ['revision' => 1])->assertForbidden();
        }
        $this->actingAs($this->owner);
        Feature::for($this->gym)->deactivate('website');
        foreach (['edit', 'preview', 'image'] as $endpoint) {
            $this->get($this->central('website.'.$endpoint))->assertNotFound();
        }
        foreach (['update', 'publish'] as $endpoint) {
            $this->post($this->central('website.'.$endpoint), $this->data())->assertNotFound();
        }
    }

    public function test_rich_text_rebuilds_safe_elements_without_attributes_and_escapes_plain_fields(): void
    {
        $payload = '<p onclick="alert(1)">Safe <strong style="x">bold</strong><a href="javascript:alert(1)">link</a><img src=x onerror=alert(1)><script>alert(1)</script><svg><script>bad</script></svg></p>';
        $this->assertSame('<p>Safe <strong>bold</strong>link</p>', WebsiteRichText::sanitize($payload));
        foreach (['<math><mtext><table><mglyph><style><!--</style><img title="--><img src=1 onerror=alert(1)>">', '<svg><foreignObject><p onmouseover="bad">x</p></foreignObject></svg>', '<!--bad--><iframe srcdoc="bad"></iframe>'] as $hostile) {
            $this->assertDoesNotMatchRegularExpression('/<(script|svg|math|iframe|img)|onerror|onmouseover|srcdoc/', WebsiteRichText::sanitize($hostile));
        }
        $this->post($this->central('website.update'), $this->data(['about' => $payload, 'description' => '<script>plain</script>']))->assertRedirect();
        $this->get($this->central('website.preview', ['section' => 'about']))->assertSee('<strong>bold</strong>', false)->assertDontSee('onclick', false)->assertDontSee('javascript:', false);
        $this->get($this->central('website.preview'))->assertSee('&lt;script&gt;plain&lt;/script&gt;', false);
    }

    public function test_images_are_reencoded_private_until_published_and_have_required_alt_text(): void
    {
        $this->post($this->central('website.update'), $this->data(['image' => UploadedFile::fake()->image('private.png', 1800, 1200)]))->assertSessionHasErrors('image_alt');
        $this->post($this->central('website.update'), $this->data(['image' => UploadedFile::fake()->image('private.png', 1800, 1200), 'image_alt' => 'Fictional gym interior']))->assertRedirect();
        $path = $this->site()->draft_content['image_path'];
        $bytes = Storage::disk('local')->get($path);
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame(1600, getimagesizefromstring($bytes)[0]);
        $this->assertStringNotContainsString('private.png', $path);
        $this->get($this->central('website.image'))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get($this->publicUrl('website-image'))->assertNotFound();
        $this->get($this->central('website.preview'))->assertSee('Fictional gym interior');
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->get($this->publicUrl('website-image'))->assertOk();
        $this->post($this->central('website.update'), $this->data(['revision' => 1, 'remove_image' => true]))->assertRedirect();
        $this->get($this->central('website.image'))->assertNotFound();
        $this->get($this->publicUrl('website-image'))->assertOk();
        $other = Gym::factory()->create();
        $this->get('http://'.$other->slug.'.'.config('tenancy.platform_domain').'/website-image')->assertNotFound();
    }

    public function test_upload_and_structured_content_validation_reject_untrusted_input(): void
    {
        foreach (['-1', '1e3', '0.001', '10000000', '1,200'] as $price) {
            $data = $this->data();
            $data['plans'][0]['price_php'] = $price;
            $this->post($this->central('website.update'), $data)->assertSessionHasErrors('plans.0.price_php');
        }
        $this->post($this->central('website.update'), $this->data(['image' => UploadedFile::fake()->create('attack.svg', 10, 'image/svg+xml'), 'image_alt' => 'Example']))->assertSessionHasErrors('image');
        $this->post($this->central('website.update'), $this->data(['plans' => array_fill(0, 13, $this->data()['plans'][0])]))->assertSessionHasErrors('plans');
        $this->post($this->central('website.update'), $this->data(['contact' => ['email' => 'not-email', 'secret' => 'hidden']]))->assertSessionHasErrors(['contact', 'contact.email']);
        $this->post($this->central('website.update'), $this->data(['announcements' => [['title' => 'Test', 'body' => 'Example', 'date' => '2026-02-30']]]))->assertSessionHasErrors('announcements.0.date');
        $this->assertDatabaseCount('gym_websites', 0);
    }

    public function test_logo_is_private_reencoded_published_and_removed_without_losing_hero(): void
    {
        $this->post($this->central('website.update'), $this->data(['logo' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')]))->assertSessionHasErrors('logo');
        $this->post($this->central('website.update'), $this->data(['logo' => UploadedFile::fake()->createWithContent('corrupt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))]))->assertSessionHasErrors('logo');
        $source = imagecreatetruecolor(1000, 1000);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, imagecolorallocatealpha($source, 255, 0, 0, 127));
        ob_start();
        imagepng($source);
        $bytes = ob_get_clean();
        imagedestroy($source);
        $this->post($this->central('website.update'), $this->data([
            'logo' => UploadedFile::fake()->createWithContent('transparent.png', $bytes),
            'image' => UploadedFile::fake()->image('hero.png'), 'image_alt' => 'Gym hero',
        ]))->assertRedirect();
        $logo = $this->site()->draft_content['logo_path'];
        $hero = $this->site()->draft_content['image_path'];
        $encoded = Storage::disk('local')->get($logo);
        $this->assertSame('RIFF', substr($encoded, 0, 4));
        $this->assertSame(512, getimagesizefromstring($encoded)[0]);
        $decoded = imagecreatefromstring($encoded);
        $this->assertSame(127, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
        imagedestroy($decoded);
        $this->get($this->central('website.image', ['asset' => 'logo']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($this->publicUrl('website-image?asset=logo'))->assertNotFound();
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->get($this->publicUrl('website-image?asset=logo'))->assertOk();
        $this->get($this->publicUrl())->assertSee('rel="icon"', false)->assertSee('asset=logo', false);
        $other = Gym::factory()->create();
        $this->get('http://'.$other->slug.'.'.config('tenancy.platform_domain').'/website-image?asset=logo')->assertNotFound();
        $this->get($this->central('website.image', ['asset' => '../logo']))->assertNotFound();
        $this->post($this->central('website.update'), $this->data(['revision' => 1, 'remove_logo' => true, 'image_alt' => 'Gym hero']))->assertRedirect();
        $this->assertSame($hero, $this->site()->draft_content['image_path']);
        $this->get($this->central('website.image', ['asset' => 'logo']))->assertNotFound();
        $this->get($this->publicUrl('website-image?asset=logo'))->assertOk();
        Storage::disk('local')->assertExists([$hero, $logo]);
        $this->post($this->central('website.publish'), ['revision' => 2])->assertRedirect();
        $this->get($this->publicUrl('website-image?asset=logo'))->assertNotFound();
        Storage::disk('local')->assertMissing($logo);
        Storage::disk('local')->assertExists($hero);
    }

    public function test_empty_sections_omitted_by_multipart_forms_are_saved_as_empty_arrays(): void
    {
        $data = $this->data(['image' => UploadedFile::fake()->image('home.png'), 'image_alt' => 'Example gym']);
        unset($data['plans'], $data['facilities'], $data['announcements']);
        $this->post($this->central('website.update'), $data)->assertRedirect();
        foreach (['plans', 'facilities', 'announcements'] as $section) {
            $this->assertSame([], $this->site()->draft_content[$section]);
        }
    }

    public function test_editor_props_are_explicit_and_paginated_lists_are_bounded(): void
    {
        $this->post($this->central('website.update'), $this->data())->assertRedirect();
        $this->get($this->central('website.edit'))->assertInertia(fn (Assert $page) => $page->component('dashboard/website/Edit')->where('revision', 1)->where('draft.plans.0.price_php', '1234.05')->missing('draft.image_path')->missing('draft.gym_id')->where('hasDraft', true));
    }

    public function test_image_replacement_keeps_live_image_until_publish_and_gym_erasure_cleans_files(): void
    {
        $this->post($this->central('website.update'), $this->data(['image' => UploadedFile::fake()->image('one.png'), 'image_alt' => 'First image']))->assertRedirect();
        $first = $this->site()->draft_content['image_path'];
        $this->get($this->central('website.preview'))->assertOk();
        $this->post($this->central('website.publish'), ['revision' => 1])->assertRedirect();
        $this->post($this->central('website.update'), $this->data(['revision' => 1, 'image' => UploadedFile::fake()->image('two.png'), 'image_alt' => 'Second image']))->assertRedirect();
        $this->post($this->central('website.update'), $this->data(['revision' => 2, 'image' => UploadedFile::fake()->image('three.png'), 'image_alt' => 'Third image']))->assertRedirect();
        // RefreshDatabase keeps an outer transaction open; production cleanup runs at commit.
        $this->assertSame($first, $this->site()->published_content['image_path']);
        $this->get($this->central('website.preview'))->assertOk();
        $this->post($this->central('website.publish'), ['revision' => 3])->assertRedirect();
        $this->assertSame($this->site()->draft_content, $this->site()->published_content);
        $this->gym->delete();
        $this->assertDatabaseCount('gym_websites', 0);
    }

    public function test_publish_audit_failure_preserves_previous_public_snapshot(): void
    {
        $this->post($this->central('website.update'), $this->data())->assertRedirect();
        $this->get($this->central('website.preview'))->assertOk();
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $this->withoutExceptionHandling()->post($this->central('website.publish'), ['revision' => 1]);
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertNull($this->site()->published_at);
        $this->assertNull($this->site()->published_content);
    }

    public function test_audit_failure_rolls_back_draft_and_removes_uploaded_file(): void
    {
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            $this->withoutExceptionHandling()->post($this->central('website.update'), $this->data(['image' => UploadedFile::fake()->image('test.png'), 'image_alt' => 'Example']));
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('gym_websites', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('websites'));
    }
}
