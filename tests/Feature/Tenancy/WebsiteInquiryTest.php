<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\GymWebsite;
use App\Models\User;
use App\Models\WebsiteInquiry;
use App\Support\WebsitePrivacy;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class WebsiteInquiryTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private GymWebsite $site;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        $this->site = new GymWebsite;
        $this->site->forceFill(['published_at' => now(), 'published_content' => $this->content()])->save();
    }

    private function content(): array
    {
        return ['privacy' => ['contact_name' => 'Fictional privacy contact', 'contact_email' => 'privacy@example.invalid', 'details' => 'Fictional gym: local development storage; no real records. Member retention is reviewed by the gym.', 'reviewed' => true, 'contact_form_enabled' => true]];
    }

    private function url(string $route = 'home', ?Gym $gym = null): string
    {
        return route('gym-site.'.$route, ['gym' => ($gym ?? $this->gym)->slug]);
    }

    private function form(): array
    {
        $response = $this->get($this->url())->assertOk();
        preg_match('/name="form_token" value="([^"]+)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->travel(4)->seconds();

        return ['name' => 'Fictional visitor', 'email' => 'visitor@example.invalid', 'message' => 'What time can I visit?', 'consent' => '1', 'form_token' => html_entity_decode($matches[1]), 'website' => ''];
    }

    private function send(array $form)
    {
        return $this->post($this->url('inquiries.store'), $form);
    }

    public function test_form_is_uncached_private_session_bound_and_consent_is_recorded_once(): void
    {
        $form = $this->form();
        $this->get($this->url())->assertHeader('Cache-Control', 'no-store, private')->assertSee('Privacy notice')->assertSee('365 days');
        $this->assertNull(Cache::get('website-page:'.$this->gym->id));
        $this->send($form)->assertRedirect($this->url().'#contact');
        $this->send($form)->assertRedirect($this->url().'#contact');
        app(CurrentGym::class)->runAs($this->gym, function () {
            $record = WebsiteInquiry::sole();
            $this->assertSame('visitor@example.invalid', $record->email);
            $this->assertSame(WebsitePrivacy::VERSION, $record->privacy_version);
            $this->assertNotNull($record->consented_at);
            $this->assertSame(1, WebsiteInquiry::count());
        });
        $this->send([...$form, 'message' => 'A different message'])->assertSessionHasErrors('form_token');
    }

    public function test_consent_and_bounded_plain_text_fields_are_required(): void
    {
        $form = $this->form();
        $this->send([...$form, 'consent' => false])->assertSessionHasErrors('consent');
        $this->send([...$form, 'email' => 'invalid', 'message' => str_repeat('x', 2001)])->assertSessionHasErrors(['email', 'message']);
        $this->assertDatabaseCount('website_inquiries', 0);
    }

    public function test_foreign_session_gym_forged_expired_and_changed_notice_proofs_fail(): void
    {
        $form = $this->form();
        $other = Gym::factory()->create();
        app(CurrentGym::class)->runAs($other, fn () => (new GymWebsite)->forceFill(['published_at' => now(), 'published_content' => $this->content()])->save());
        $this->post($this->url('inquiries.store', $other), $form)->assertSessionHasErrors('form_token');
        $this->send([...$form, 'form_token' => 'forged'])->assertSessionHasErrors('form_token');
        $changed = $this->content();
        $changed['privacy']['details'] = 'Changed public privacy practices';
        $this->site->forceFill(['published_content' => $changed])->save();
        $this->send($form)->assertSessionHasErrors('form_token');
        $form = $this->form();
        $this->travel(31)->minutes();
        $this->send($form)->assertSessionHasErrors('form_token');
        $this->travelBack();
        $form = $this->form();
        session()->migrate();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->send($form)->assertSessionHasErrors('form_token');
        $this->assertDatabaseCount('website_inquiries', 0);
    }

    public function test_honeypot_and_too_fast_submissions_cannot_create_records(): void
    {
        $form = $this->form();
        $this->send([...$form, 'website' => 'spam'])->assertRedirect();
        $this->travel(-4)->seconds();
        $this->send($form)->assertSessionHasErrors('form_token');
        $this->assertDatabaseCount('website_inquiries', 0);
    }

    public function test_unpublished_disabled_and_draft_only_forms_are_unavailable(): void
    {
        $form = $this->form();
        $disabled = $this->content();
        $disabled['privacy']['contact_form_enabled'] = false;
        $this->site->forceFill(['published_content' => $disabled, 'draft_content' => $this->content()])->save();
        $this->get($this->url())->assertDontSee('name="form_token"', false);
        $this->send($form)->assertNotFound();
        $this->site->forceFill(['published_at' => null])->save();
        $this->send($form)->assertNotFound();
        Feature::for($this->gym)->deactivate('website');
        $this->get($this->url())->assertNotFound();
    }

    public function test_owner_inbox_is_paginated_escaped_explicit_and_other_roles_are_blocked(): void
    {
        $form = $this->form();
        $this->send([...$form, 'message' => '<script>window.bad=true</script>'])->assertRedirect();
        app(CurrentGym::class)->runAs($this->gym, function () {
            for ($i = 0; $i < 10; $i++) {
                (new WebsiteInquiry)->forceFill(['name' => 'Sample', 'email' => 'sample@example.invalid', 'message' => 'Sample message', 'request_hash' => hash('sha256', (string) $i), 'content_hash' => str_repeat('a', 64), 'privacy_version' => WebsitePrivacy::VERSION, 'privacy_revision' => str_repeat('b', 64), 'consented_at' => now()])->save();
            }
        });
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
        $this->get('http://localhost/gym/website/inquiries')->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->component('dashboard/website/Inquiries')->has('inquiries.data', 10)->where('inquiries.last_page', 2)->missing('inquiries.data.0.gym_id')->missing('inquiries.data.0.request_hash'));
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Staff)->create());
        $this->get('http://localhost/gym/website/inquiries')->assertForbidden();
    }

    public function test_rate_limit_rejects_sixth_submission(): void
    {
        $form = $this->form();
        for ($i = 0; $i < 5; $i++) {
            $this->send($form)->assertRedirect();
        }
        $this->send($form)->assertStatus(429);
    }

    public function test_retention_prunes_all_tenants_at_the_boundary_and_gym_deletion_cleans_messages(): void
    {
        $this->send($this->form())->assertRedirect();
        app(CurrentGym::class)->runAs($this->gym, fn () => WebsiteInquiry::sole()->forceFill(['created_at' => now()->subDays(365)])->save());
        $other = Gym::factory()->create();
        app(CurrentGym::class)->runAs($other, function () {
            foreach ([365, 364] as $days) {
                (new WebsiteInquiry)->forceFill(['name' => 'Foreign visitor', 'email' => 'foreign@example.invalid', 'message' => 'Private foreign inquiry', 'request_hash' => hash('sha256', (string) $days), 'content_hash' => str_repeat('a', 64), 'privacy_version' => WebsitePrivacy::VERSION, 'privacy_revision' => str_repeat('b', 64), 'consented_at' => now()->subDays($days), 'created_at' => now()->subDays($days)])->save();
            }
        });
        app(CurrentGym::class)->forget();
        $this->artisan('model:prune', ['--model' => [WebsiteInquiry::class]])->assertSuccessful();
        $this->assertDatabaseCount('website_inquiries', 1);
        app(CurrentGym::class)->runAs($other, fn () => $this->assertSame('Foreign visitor', WebsiteInquiry::sole()->name));
        $this->send($this->form())->assertRedirect();
        $this->gym->delete();
        $this->assertDatabaseCount('website_inquiries', 1);
    }

    public function test_enablement_requires_review_and_details_and_preview_cannot_submit(): void
    {
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
        $data = ['revision' => 0, 'contact' => ['phone' => null], 'plans' => [], 'facilities' => [], 'announcements' => [], 'privacy' => ['contact_form_enabled' => true]];
        $this->post('http://localhost/gym/website', $data)->assertSessionHasErrors(['privacy.contact_name', 'privacy.contact_email', 'privacy.details', 'privacy.reviewed']);
        $data['privacy'] = $this->content()['privacy'];
        $this->post('http://localhost/gym/website', $data)->assertRedirect();
        $this->get('http://localhost/gym/website/preview')->assertSee('Preview only.')->assertSee('disabled', false)->assertSee('Privacy notice');
    }

    public function test_csrf_is_required_outside_testing_environment(): void
    {
        $this->app['env'] = 'local';
        $form = $this->form();
        $this->send($form)->assertStatus(419);
        $this->send([...$form, '_token' => session()->token()])->assertRedirect();
    }

    public function test_form_feedback_from_another_gym_is_not_rendered(): void
    {
        $form = $this->form();
        $this->send([...$form, 'email' => 'invalid'])->assertSessionHasErrors('email');
        $other = Gym::factory()->create();
        app(CurrentGym::class)->runAs($other, fn () => (new GymWebsite)->forceFill(['published_at' => now(), 'published_content' => $this->content()])->save());
        $this->get($this->url('home', $other))->assertDontSee('Fictional visitor')->assertDontSee('Please check the form.');
    }
}
