<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\AccessRequest;
use App\Models\OwnerInvitation;
use App\Models\User;
use App\Notifications\AccessRequestReceived;
use App\Notifications\OwnerInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessRequestTest extends TestCase
{
    use RefreshDatabase;

    private array $valid = [
        'name' => 'Dina Santos',
        'email' => 'Dina@DMFitness.test',
        'phone' => '09171234567',
        'gym_name' => 'DM Fitness',
        'location' => 'Cubao, QC',
        'plan' => 'pro',
        'message' => 'We have about 200 members.',
        'consent' => '1',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function submit(array $overrides = []): TestResponse
    {
        // A real person: the form was opened 10 seconds ago.
        $started = Crypt::encryptString((string) now()->subSeconds(10)->timestamp);

        return $this->post(route('access-requests.store'), [...$this->valid, 'form_started' => $started, ...$overrides]);
    }

    public function test_request_access_buttons_open_the_form_with_the_plan_selected()
    {
        $this->get(route('home'))->assertSee(route('access-requests.create', ['plan' => 'pro']), false);

        $this->get(route('access-requests.create', ['plan' => 'multi_branch']))
            ->assertOk()
            ->assertSee('Request access')
            ->assertSee('<option value="multi_branch" selected', false);
    }

    public function test_the_form_works_without_javascript()
    {
        Vite::useHotFile(storage_path('framework/testing-no-hot-file')); // render like production
        $html = $this->get(route('access-requests.create'))->getContent();

        $this->assertStringContainsString('<form method="POST" action="'.route('access-requests.store').'"', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_a_request_is_saved_and_the_platform_admin_is_emailed()
    {
        $this->submit()->assertRedirect(route('access-requests.create'))->assertSessionHas('status', 'sent');

        $request = AccessRequest::sole();
        $this->assertSame('dina@dmfitness.test', $request->email);
        $this->assertSame(Plan::Pro, $request->plan);
        $this->assertSame('new', $request->status);

        Notification::assertSentTo(new AnonymousNotifiable, AccessRequestReceived::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === config('platform.access_email'));

        $this->get(route('access-requests.create'))->assertSee('Request sent');
    }

    public function test_required_fields_and_consent_are_checked()
    {
        $this->submit(['name' => '', 'email' => 'nope', 'gym_name' => '', 'plan' => 'free', 'consent' => null])
            ->assertSessionHasErrors(['name', 'email', 'gym_name', 'plan', 'consent']);

        $this->assertSame(0, AccessRequest::count());
        Notification::assertNothingSent();
    }

    public function test_bots_filling_the_hidden_field_are_ignored_quietly()
    {
        $this->submit(['website' => 'http://spam.example'])->assertSessionHas('status', 'sent');

        $this->assertSame(0, AccessRequest::count());
        Notification::assertNothingSent();
    }

    public function test_submissions_faster_than_a_human_are_ignored_quietly()
    {
        $this->submit(['form_started' => Crypt::encryptString((string) now()->timestamp)])->assertSessionHas('status', 'sent');
        $this->submit(['form_started' => null])->assertSessionHas('status', 'sent');
        $this->submit(['form_started' => 'forged-value'])->assertSessionHas('status', 'sent');

        $this->assertSame(0, AccessRequest::count());
        Notification::assertNothingSent();
    }

    public function test_the_same_email_cannot_flood_the_inbox()
    {
        $this->submit();
        $this->submit(['name' => 'Again'])->assertSessionHas('status', 'sent');

        $this->assertSame(1, AccessRequest::count());
        Notification::assertSentTimes(AccessRequestReceived::class, 1);

        // A day later they may ask again.
        $this->travel(25)->hours();
        $this->submit();
        $this->assertSame(2, AccessRequest::count());
    }

    public function test_there_is_an_overall_cap_across_all_visitors()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->submit(['email' => "owner{$i}@example.com"]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->submit(['email' => 'late@example.com'])
            ->assertTooManyRequests();

        $this->assertSame(30, AccessRequest::count());
    }

    public function test_old_requests_are_pruned()
    {
        $this->submit(['email' => 'handled@example.com']);
        $this->submit(['email' => 'ignored@example.com']);
        AccessRequest::where('email', 'handled@example.com')->update(['status' => 'invited', 'updated_at' => now()->subDays(91)]);
        AccessRequest::where('email', 'ignored@example.com')->update(['created_at' => now()->subDays(181)]);
        $this->submit(['email' => 'fresh@example.com']);

        $this->artisan('model:prune', ['--model' => AccessRequest::class])->assertSuccessful();

        $this->assertSame(['fresh@example.com'], AccessRequest::pluck('email')->all());
    }

    public function test_requests_are_rate_limited_per_visitor()
    {
        for ($i = 0; $i < 3; $i++) {
            $this->submit(['email' => "owner{$i}@example.com"]);
        }

        $this->submit(['email' => 'owner4@example.com'])->assertTooManyRequests();
        $this->assertSame(3, AccessRequest::count());
    }

    public function test_platform_admin_sees_requests_and_sends_access_in_one_click()
    {
        $this->submit();
        $request = AccessRequest::sole();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get(route('platform.owner-invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('accessRequests', 1)
                ->where('accessRequests.0.gymName', 'DM Fitness')
                ->where('accessRequests.0.plan', 'pro'));

        $this->actingAs($admin)->post(route('platform.owner-invitations.store'), [
            'email' => $request->email, 'plan' => 'pro', 'gym_name' => 'DM Fitness', 'access_request_id' => $request->id,
        ]);

        $this->assertSame('invited', $request->refresh()->status);
        $this->assertSame('dina@dmfitness.test', OwnerInvitation::sole()->email);
        Notification::assertSentTo(new AnonymousNotifiable, OwnerInvitationNotification::class);

        $this->actingAs($admin)->get(route('platform.owner-invitations.index'))
            ->assertInertia(fn (Assert $page) => $page->has('accessRequests', 0));
    }

    public function test_dismissing_a_request_deletes_it()
    {
        $this->submit();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->delete(route('platform.access-requests.destroy', AccessRequest::sole()))
            ->assertRedirect(route('platform.owner-invitations.index'));

        $this->assertSame(0, AccessRequest::count());
    }

    public function test_only_the_platform_admin_can_see_or_dismiss_requests()
    {
        $this->submit();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('platform.owner-invitations.index'))->assertForbidden();
        $this->actingAs($user)->delete(route('platform.access-requests.destroy', AccessRequest::sole()))->assertForbidden();
    }
}
