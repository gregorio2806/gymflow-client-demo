<?php

namespace Tests\Feature\Portal;

use App\Enums\GymRole;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\GymWebsite;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MembershipCheckTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private string $code = '1A2B3C4D5E6F708192AB';

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        Feature::for($this->gym)->activate('member-auth');
        $this->member = Member::factory()->create(['name' => 'Fictional Lookup Member', 'mobile' => '09171234567', 'email' => 'member@example.test']);
        $this->member->forceFill(['status_code_hash' => hash('sha256', $this->code)])->save();
        $plan = MembershipPlan::factory()->create();
        (new MemberSubscription)->forceFill(['member_id' => $this->member->id, 'membership_plan_id' => $plan->id, 'plan_name' => 'Private plan', 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 10000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(29), 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();
    }

    private function check($value, ?Gym $gym = null)
    {
        return $this->postJson(route('portal.check-status', ['gym' => ($gym ?? $this->gym)->slug]), ['code' => $value]);
    }

    public function test_lookup_page_uses_only_this_gyms_published_branding_when_website_is_enabled(): void
    {
        (new GymWebsite)->forceFill([
            'published_at' => now()->utc(),
            'published_content' => ['gym_name' => 'Published Gym Name', 'logo_path' => 'gyms/lookup/logo.webp', 'image_path' => 'gyms/lookup/hero.webp'],
            'draft_content' => ['gym_name' => 'Private Draft Name', 'logo_path' => 'gyms/lookup/private-logo.webp', 'image_path' => 'gyms/lookup/private-hero.webp'],
        ])->save();
        $other = Gym::factory()->create();
        app(CurrentGym::class)->runAs($other, fn () => (new GymWebsite)->forceFill(['published_at' => now()->utc(), 'published_content' => ['gym_name' => 'Other Gym Brand']])->save());

        $endpoint = route('portal.check', ['gym' => $this->gym->slug]);
        $this->get($endpoint)->assertOk()->assertDontSee('Private Draft Name')->assertDontSee('Other Gym Brand')
            ->assertInertia(fn (Assert $page) => $page->component('portal/CheckMembership')->where('gymName', 'Published Gym Name')
                ->where('homeUrl', route('gym-site.home', ['gym' => $this->gym->slug], false))
                ->where('logoUrl', route('gym-site.image', ['gym' => $this->gym->slug, 'asset' => 'logo', 'v' => substr(hash('sha256', 'gyms/lookup/logo.webp'), 0, 16)], false))
                ->where('imageUrl', route('gym-site.image', ['gym' => $this->gym->slug, 'v' => substr(hash('sha256', 'gyms/lookup/hero.webp'), 0, 16)], false))
                ->where('qrAttendanceAvailable', true)
                ->missing('draft_content')->missing('published_content'));
        Feature::for($this->gym)->deactivate('website');
        $this->get($endpoint)->assertOk()->assertInertia(fn (Assert $page) => $page->where('gymName', $this->gym->name)->where('homeUrl', null)->where('logoUrl', null)->where('imageUrl', null));
    }

    public function test_code_returns_only_status_without_login_or_check_in(): void
    {
        $this->check('1a2b-3c4d-5e6f-7081-92ab')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['status' => 'Active']);
        $this->assertGuest('member');
        $this->assertGuest('web');
        $this->assertDatabaseCount('attendance_records', 0);
        $this->travel(40)->days();
        $this->check($this->code)->assertExactJson(['status' => 'Expired']);
    }

    public function test_qr_purposes_resolve_and_revoked_or_expired_credentials_fail(): void
    {
        foreach (['attendance', 'portal'] as $purpose) {
            app(CurrentGym::class)->set($this->gym);
            $raw = Str::random(64);
            $token = new MemberQrToken;
            $token->forceFill(['member_id' => $this->member->id, 'purpose' => $purpose, 'token_hash' => hash('sha256', $raw)])->save();
            $input = $purpose === 'attendance' ? 'gym-attendance:'.$raw : route('portal.access', ['gym' => $this->gym->slug]).'#'.$raw;
            $this->check($input)->assertExactJson(['status' => 'Active']);
            $token->forceFill(['revoked_at' => now()])->save();
            $this->check($input)->assertUnprocessable();
        }
        $raw = Str::random(64);
        app(CurrentGym::class)->set($this->gym);
        (new MemberQrToken)->forceFill(['member_id' => $this->member->id, 'purpose' => 'attendance', 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->subMinute()])->save();
        $this->check($raw)->assertUnprocessable();
    }

    public function test_foreign_gym_invalid_input_inactive_member_and_disabled_feature_fail(): void
    {
        $other = Gym::factory()->create();
        Feature::for($other)->activate('member-auth');
        $this->check($this->code, $other)->assertUnprocessable();
        foreach ([$this->member->id, ['bad'], str_repeat('a', 1001), 'member@example.invalid'] as $value) {
            $this->check($value)->assertUnprocessable();
        }
        $this->member->forceFill(['is_active' => false])->save();
        $this->check($this->code)->assertUnprocessable();
        Feature::for($this->gym)->deactivate('member-portal');
        $this->check($this->code)->assertNotFound();
    }

    public function test_staff_code_issuance_is_one_time_hashed_replaceable_and_revocable(): void
    {
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
        $endpoint = route('member-portal.store', $this->member);
        $result = $this->postJson($endpoint, ['operation' => 'issue-status-code'])->assertOk()->json('statusCode');
        $this->assertMatchesRegularExpression('/\A[A-F0-9]{4}(?:-[A-F0-9]{4}){4}\z/', $result);
        $this->assertSame(hash('sha256', str_replace('-', '', $result)), $this->member->refresh()->status_code_hash);
        $this->assertStringNotContainsString($result, AuditLog::sole()->toJson());
        $this->check($this->code)->assertUnprocessable();
        $this->check($result)->assertOk();
        $this->postJson('http://localhost/members/'.$this->member->id.'/portal', ['operation' => 'revoke-status-code'])->assertOk();
        $this->check($result)->assertUnprocessable();
    }

    public function test_status_lookup_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            if ($i % 2 === 0) {
                $this->check('bad')->assertUnprocessable();
            } else {
                $this->postJson(route('portal.check-status', ['gym' => $this->gym->slug]), ['contact' => 'not-a-real-contact'])->assertUnprocessable();
            }
        }
        $this->check('bad')->assertStatus(429);
    }

    public function test_guest_contact_returns_only_membership_details_in_gym_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 00:30:00', 'UTC'));
        $member = Member::factory()->create(['name' => 'Fictional Lookup Member', 'email' => 'monthly@example.test']);
        $plan = MembershipPlan::factory()->create();
        (new MemberSubscription)->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => 'Monthly access', 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000, 'starts_at' => '2026-10-06 16:00:00', 'ends_at' => '2026-11-05 16:00:00', 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();
        $expected = ['maskedName' => 'F*******l L****p M****r', 'planName' => 'Monthly access', 'startsOn' => '2026-10-07', 'validThrough' => '2026-11-05', 'status' => 'Active'];
        $endpoint = route('portal.check-status', ['gym' => $this->gym->slug]);

        $this->postJson($endpoint, ['contact' => 'monthly@example.test'])->assertOk()->assertExactJson($expected)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest('member');
        $this->assertGuest('web');
        $this->assertDatabaseCount('attendance_records', 0);
        $this->get(route('portal.home', ['gym' => $this->gym->slug]))->assertRedirect(route('portal.login', ['gym' => $this->gym->slug]));
    }

    public function test_public_lookup_accepts_phone_or_email_and_rejects_member_id_and_name_search(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->member->update(['name' => 'Fictional Lookup Member']);
        $endpoint = route('portal.check-status', ['gym' => $this->gym->slug]);
        foreach ([[], ['contact' => 0], ['contact' => ['bad']], ['contact' => '1 OR 1=1'], ['contact' => str_repeat('9', 256)], ['member_id' => $this->member->id], ['member_id' => $this->member->id, 'code' => $this->code]] as $input) {
            $this->postJson($endpoint, $input)->assertUnprocessable();
        }
        foreach ([['full_name' => $this->member->name], ['contact' => $this->member->email, 'full_name' => $this->member->name], ['code' => $this->code, 'full_name' => $this->member->name]] as $input) {
            $this->postJson($endpoint, $input)->assertUnprocessable()->assertJsonValidationErrors('full_name');
        }
        $error = ['errors' => ['lookup' => ['No matching member was found. Check your phone number or email, or ask the gym.']]];
        $this->postJson($endpoint, ['contact' => 'not-a-member@example.test'])->assertUnprocessable()->assertExactJson($error);
        $this->postJson($endpoint, ['contact' => '(0917) 123-4567'])->assertOk()->assertJsonPath('planName', 'Private plan');
        $this->postJson($endpoint, ['contact' => 'MEMBER@EXAMPLE.TEST'])->assertOk()->assertJsonPath('planName', 'Private plan');
        $this->member->forceFill(['is_active' => false])->save();
        $this->postJson($endpoint, ['contact' => $this->member->email])->assertUnprocessable()->assertExactJson($error);
    }

    public function test_guest_contact_can_generate_an_attendance_qr_once_without_exposing_the_secret(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $endpoint = route('portal.check-attendance-qr', ['gym' => $this->gym->slug]);

        $response = $this->postJson($endpoint, ['contact' => 'MEMBER@EXAMPLE.TEST'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $payload = $response->json('payload');

        $this->assertMatchesRegularExpression('/\Agym-attendance:[A-Za-z0-9]{64}\z/', $payload);
        $token = MemberQrToken::withoutGlobalScopes()->sole();
        $this->assertSame(hash('sha256', substr($payload, strlen('gym-attendance:'))), $token->token_hash);
        $this->assertSame('attendance', $token->purpose->value);
        $audit = AuditLog::withoutGlobalScopes()->where('action', 'attendance-qr.generated')->sole();
        $this->assertStringNotContainsString($payload, $audit->toJson());
        $this->assertStringNotContainsString($token->token_hash, $audit->toJson());
        $this->assertGuest('web');

        $this->postJson($endpoint, ['contact' => $this->member->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operation');
        $this->postJson($endpoint, ['member_id' => $this->member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact');
    }

    public function test_public_attendance_qr_generation_is_disabled_with_the_feature(): void
    {
        Feature::for($this->gym)->deactivate('qr-attendance');

        $this->postJson(route('portal.check-attendance-qr', ['gym' => $this->gym->slug]), ['contact' => $this->member->email])
            ->assertNotFound();
    }

    public function test_guest_lookup_cannot_read_another_gyms_member(): void
    {
        $other = Gym::factory()->create();
        Feature::for($other)->activate('member-auth');
        $endpoint = route('portal.check-status', ['gym' => $other->slug]);
        $this->postJson($endpoint, ['contact' => $this->member->email])->assertUnprocessable();
        app(CurrentGym::class)->set($other);
        $member = Member::factory()->create(['name' => 'No Plan Member', 'mobile' => '09170000000', 'email' => 'other@example.test']);
        $this->postJson($endpoint, ['contact' => $member->email])->assertExactJson(['maskedName' => 'N* P**n M****r', 'planName' => null, 'startsOn' => null, 'validThrough' => null, 'status' => 'No membership assigned']);
        Feature::for($other)->deactivate('member-portal');
        $this->postJson($endpoint, ['contact' => $member->email])->assertNotFound();
    }

    public function test_public_lookup_uses_current_membership_status_and_retains_expired_dates(): void
    {
        $period = $this->member->subscriptions()->sole();
        $endpoint = route('portal.check-status', ['gym' => $this->gym->slug]);
        $details = ['maskedName' => 'F*******l L****p M****r', 'planName' => $period->plan_name, 'startsOn' => $period->starts_at->setTimezone($this->gym->timezone)->toDateString(), 'validThrough' => $period->ends_at->subSecond()->setTimezone($this->gym->timezone)->toDateString()];
        $period->forceFill(['status' => 'suspended'])->save();
        $this->postJson($endpoint, ['contact' => $this->member->email])->assertExactJson([...$details, 'status' => 'Suspended']);
        $this->travel(40)->days();
        $this->postJson($endpoint, ['contact' => $this->member->email])->assertExactJson([...$details, 'status' => 'Expired']);
    }

    public function test_guest_name_is_masked_on_the_server_including_short_and_unicode_names(): void
    {
        $endpoint = route('portal.check-status', ['gym' => $this->gym->slug]);
        foreach (['Juan Dela Cruz' => 'J**n D**a C**z', 'Li A' => 'L* *', 'José Ñu' => 'J**é Ñ*'] as $name => $masked) {
            $this->member->update(['name' => $name]);
            $response = $this->postJson($endpoint, ['contact' => $this->member->email])->assertOk()->assertJsonPath('maskedName', $masked);
            $this->assertCount(5, $response->json());
            $this->assertArrayNotHasKey('name', $response->json());
            $this->assertArrayNotHasKey('memberName', $response->json());
            $this->assertStringNotContainsString(json_encode($name, JSON_UNESCAPED_UNICODE), json_encode($response->json(), JSON_UNESCAPED_UNICODE));
        }
    }
}
