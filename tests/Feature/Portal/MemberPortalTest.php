<?php

namespace Tests\Feature\Portal;

use App\Enums\GymRole;
use App\Enums\MemberQrPurpose;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Models\MemberRoutineAssignment;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Models\WorkoutRoutine;
use App\Support\CalorieEstimate;
use App\Support\LegalDocuments;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
        Feature::for($this->gym)->activate('member-auth');
        $this->member = Member::factory()->create();
        $this->member->forceFill(['terms_version' => LegalDocuments::bundle()['version'], 'terms_accepted_at' => now(), 'portal_login' => 'test-member', 'portal_password' => Hash::make('test-password')])->save();
    }

    private function url(string $name): string
    {
        return route('portal.'.$name, ['gym' => $this->gym->slug]);
    }

    private function retainCookie(): void
    {
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function login(): void
    {
        $this->post($this->url('authenticate'), ['login' => 'TEST-MEMBER', 'password' => 'test-password'])->assertRedirect($this->url('home'));
        $this->retainCookie();
    }

    private function token(MemberQrPurpose $purpose = MemberQrPurpose::Portal): array
    {
        return app(CurrentGym::class)->runAs($this->gym, function () use ($purpose) {
            $raw = Str::random(64);
            $token = new MemberQrToken;
            $token->forceFill(['member_id' => $this->member->id, 'purpose' => $purpose, 'token_hash' => hash('sha256', $raw)])->save();

            return [$raw, $token];
        });
    }

    private function assignedWorkout(?Member $member = null, string $name = 'Saved member workout'): MemberRoutineAssignment
    {
        $member ??= $this->member;

        return app(CurrentGym::class)->runAs($this->gym, function () use ($member, $name) {
            $snapshot = ['name' => $name, 'notes' => 'Controlled movements', 'days' => [['name' => 'Monday', 'exercises' => array_map(fn ($i) => ['name' => 'Exercise '.$i, 'sets' => 3, 'reps' => '8-12', 'notes' => 'Steady tempo'], range(1, 6))], ['name' => 'Thursday', 'exercises' => [['name' => 'Walking', 'sets' => 1, 'reps' => '20 minutes', 'notes' => 'Comfortable pace']]]]];
            $routine = (new WorkoutRoutine)->forceFill([...$snapshot, 'branch_id' => $member->home_branch_id, 'created_by' => null, 'request_key' => (string) Str::uuid(), 'revision' => 1]);
            $routine->save();
            $assignment = (new MemberRoutineAssignment)->forceFill(['branch_id' => $member->home_branch_id, 'member_id' => $member->id, 'workout_routine_id' => $routine->id, 'assigned_by' => null, 'routine_revision' => 1, 'snapshot' => $snapshot, 'assigned_at' => '2026-10-06 00:00:00']);
            $assignment->save();

            return $assignment;
        });
    }

    private function calorieData(array $changes = []): array
    {
        return array_replace(['age' => 30, 'height_cm' => 170, 'weight_kg' => 70, 'equation' => 'male', 'activity' => 'sedentary', 'goal' => 'maintain', 'consent' => true, 'eligible' => true, 'consent_version' => CalorieEstimate::CONSENT_VERSION], $changes);
    }

    public function test_calorie_inputs_require_consent_are_encrypted_and_retries_are_safe(): void
    {
        Feature::for($this->gym)->activate('calorie-helper');
        $this->login();
        $this->postJson($this->url('calorie-save'), $this->calorieData(['age' => 17]))->assertUnprocessable();
        $this->postJson($this->url('calorie-save'), $this->calorieData(['consent_version' => 'old']))->assertUnprocessable();
        $data = $this->calorieData(['member_id' => 999, 'gym_id' => 999]);
        $this->postJson($this->url('calorie-save'), $data)->assertOk();
        $this->postJson($this->url('calorie-save'), $data)->assertOk();
        $this->assertDatabaseCount('member_calorie_profiles', 1);
        $raw = DB::table('member_calorie_profiles')->sole();
        $this->assertSame($this->member->id, $raw->member_id);
        $this->assertStringNotContainsString('weight_kg', $raw->details);
        $log = AuditLog::withoutGlobalScope('gym')->where('gym_id', $this->gym->id)->where('action', 'calorie-profile.saved')->sole();
        $this->assertNull($log->user_id);
        $this->assertStringNotContainsString('weight_kg', json_encode($log->changes));
        $this->get($this->url('calorie'))->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->component('portal/CalorieHelper')->where('profile.age', 30)->where('estimate.resting', 1618)->where('estimate.maintenance', 1950)->missing('profile.member_id')->where('auth.user', null));
        $this->postJson($this->url('calorie-save'), $this->calorieData(['activity' => 'very_active']))->assertOk();
        $this->get($this->url('calorie'))->assertInertia(fn (Assert $page) => $page->where('profile.activity', 'very_active')->where('estimate.maintenance', 3050));
    }

    public function test_calorie_feature_defaults_off_and_deletion_remains_available_when_disabled(): void
    {
        $this->login();
        $this->get($this->url('calorie'))->assertNotFound();
        $this->postJson($this->url('calorie-save'), $this->calorieData())->assertNotFound();
        Feature::for($this->gym)->activate('calorie-helper');
        $this->postJson($this->url('calorie-save'), $this->calorieData())->assertOk();
        Feature::for($this->gym)->deactivate('calorie-helper');
        $this->get($this->url('home'))->assertInertia(fn (Assert $page) => $page->where('calorieHelperAvailable', true));
        $this->get($this->url('calorie'))->assertInertia(fn (Assert $page) => $page->where('enabled', false)->where('profile', null)->where('estimate', null)->where('hasProfile', true));
        $this->deleteJson($this->url('calorie-delete'))->assertOk();
        $this->deleteJson($this->url('calorie-delete'))->assertOk();
        $this->assertDatabaseCount('member_calorie_profiles', 0);
        $this->assertSame(1, AuditLog::withoutGlobalScope('gym')->where('gym_id', $this->gym->id)->where('action', 'calorie-profile.deleted')->count());
        $this->get($this->url('home'))->assertInertia(fn (Assert $page) => $page->where('calorieHelperAvailable', false));
    }

    public function test_calorie_other_members_tenants_and_staff_cannot_access_or_delete_inputs(): void
    {
        Feature::for($this->gym)->activate('calorie-helper');
        $staff = User::factory()->memberOf($this->gym, GymRole::Staff)->create();
        $this->actingAs($staff)->get($this->url('calorie'))->assertRedirect($this->url('login'));
        $this->retainCookie();
        $this->login();
        $this->postJson($this->url('calorie-save'), $this->calorieData())->assertOk();
        $this->assertNull(AuditLog::withoutGlobalScope('gym')->where('gym_id', $this->gym->id)->where('action', 'calorie-profile.saved')->sole()->user_id);
        $other = Gym::factory()->create();
        Feature::for($other)->activate('member-auth');
        $this->deleteJson(route('portal.calorie-delete', ['gym' => $other->slug]))->assertRedirect(route('portal.login', ['gym' => $other->slug]));
        $this->assertDatabaseCount('member_calorie_profiles', 1);
        $different = app(CurrentGym::class)->runAs($this->gym, fn () => Member::factory()->create());
        $different->forceFill(['terms_version' => LegalDocuments::bundle()['version'], 'terms_accepted_at' => now(), 'portal_login' => 'other-member', 'portal_password' => Hash::make('test-password')])->save();
        $this->post($this->url('authenticate'), ['login' => 'other-member', 'password' => 'test-password']);
        $this->retainCookie();
        $this->get($this->url('calorie').'?member_id='.$this->member->id)->assertInertia(fn (Assert $page) => $page->where('profile', null));
        $this->deleteJson($this->url('calorie-delete'), ['member_id' => $this->member->id])->assertOk();
        $this->assertDatabaseCount('member_calorie_profiles', 1);
    }

    public function test_calorie_validation_never_flashes_health_inputs_to_session(): void
    {
        Feature::for($this->gym)->activate('calorie-helper');
        $this->login();
        $this->post($this->url('calorie-save'), $this->calorieData(['height_cm' => 999]))->assertUnprocessable()->assertSessionMissing('_old_input');
        $this->assertDatabaseCount('member_calorie_profiles', 0);
        $this->member->forceFill(['is_active' => false])->save();
        $this->postJson($this->url('calorie-save'), $this->calorieData())->assertRedirect($this->url('login'));
    }

    public function test_calorie_audit_failure_rolls_back_saved_inputs(): void
    {
        Feature::for($this->gym)->activate('calorie-helper');
        $this->login();
        AuditLog::creating(function ($log) {
            if ($log->action === 'calorie-profile.saved') {
                throw new \RuntimeException('Audit unavailable');
            }
        });
        try {
            $this->postJson($this->url('calorie-save'), $this->calorieData())->assertStatus(500);
            $this->assertDatabaseCount('member_calorie_profiles', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_workout_requires_verified_member_and_only_returns_their_snapshot(): void
    {
        $this->assignedWorkout();
        $other = Member::factory()->create(['name' => 'Private other member']);
        $this->assignedWorkout($other, 'Private other workout');
        $this->get($this->url('workouts'))->assertRedirect($this->url('login'));
        $this->retainCookie();
        $this->login();
        $this->get($this->url('workouts').'?member_id='.$other->id.'&routine_id=999')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertInertia(fn (Assert $page) => $page->component('portal/Workouts')->where('routine.name', 'Saved member workout')->where('routine.assignedAt', 'Oct 6, 2026 8:00 AM')->where('routine.days', ['Monday', 'Thursday'])->where('auth.user', null)->where('currentGym', null)->has('exercises.data', 5)->missing('routine.id')->missing('routine.assigned_by')->missing('routine.member_id')->missing('member.mobile')->missing('portal_password'))->assertDontSee('Private other workout');
    }

    public function test_workout_days_and_exercises_are_paginated_and_validated(): void
    {
        $this->assignedWorkout();
        $this->login();
        $this->get($this->url('workouts').'?day=1&page=2')->assertInertia(fn (Assert $page) => $page->has('exercises.data', 1)->where('exercises.data.0.name', 'Exercise 6')->where('exercises.total', 6)->where('routine.selectedDay', 1));
        $this->get($this->url('workouts').'?day=2')->assertInertia(fn (Assert $page) => $page->has('exercises.data', 1)->where('exercises.data.0.name', 'Walking')->where('exercises.current_page', 1));
        $this->get($this->url('workouts').'?day=3')->assertNotFound();
        $this->getJson($this->url('workouts').'?day[]=1')->assertUnprocessable();
        $this->getJson($this->url('workouts').'?page=101')->assertUnprocessable();
    }

    public function test_empty_ended_and_transferred_assignments_do_not_show_a_workout(): void
    {
        $this->login();
        $this->get($this->url('workouts'))->assertInertia(fn (Assert $page) => $page->where('routine', null)->where('exercises', null));
        $assignment = $this->assignedWorkout();
        $assignment->ended_at = now();
        $assignment->save();
        $this->get($this->url('workouts'))->assertInertia(fn (Assert $page) => $page->where('routine', null));
        $this->assignedWorkout();
        $branch = Branch::factory()->create(['gym_id' => $this->gym->id]);
        $this->member->forceFill(['home_branch_id' => $branch->id])->save();
        $this->get($this->url('workouts'))->assertInertia(fn (Assert $page) => $page->where('routine', null));
    }

    public function test_workout_keeps_assigned_version_until_reassigned_and_is_read_only(): void
    {
        $assignment = $this->assignedWorkout();
        $routine = WorkoutRoutine::findOrFail($assignment->workout_routine_id);
        $routine->forceFill(['name' => 'Unassigned new name', 'revision' => 2])->save();
        $this->login();
        $this->get($this->url('workouts'))->assertInertia(fn (Assert $page) => $page->where('routine.name', 'Saved member workout')->where('routine.version', 1));
        $this->post($this->url('workouts'), ['name' => 'Tampered'])->assertStatus(405);
        $assignment->ended_at = now();
        $assignment->save();
        app(CurrentGym::class)->runAs($this->gym, fn () => (new MemberRoutineAssignment)->forceFill(['branch_id' => $this->member->home_branch_id, 'member_id' => $this->member->id, 'workout_routine_id' => $routine->id, 'assigned_by' => null, 'routine_revision' => 2, 'snapshot' => $routine->only(['name', 'notes', 'days']), 'assigned_at' => now()])->save());
        $this->get($this->url('workouts'))->assertInertia(fn (Assert $page) => $page->where('routine.name', 'Unassigned new name')->where('routine.version', 2));
    }

    public function test_workout_is_tenant_and_feature_scoped(): void
    {
        $this->assignedWorkout();
        $this->login();
        $other = Gym::factory()->create();
        Feature::for($other)->activate('member-auth');
        $this->get(route('portal.workouts', ['gym' => $other->slug]))->assertRedirect(route('portal.login', ['gym' => $other->slug]));
        $this->login();
        Feature::for($this->gym)->deactivate('workouts');
        $this->get($this->url('home'))->assertInertia(fn (Assert $page) => $page->where('workoutsEnabled', false));
        $this->get($this->url('workouts'))->assertNotFound();
        Feature::for($this->gym)->activate('workouts');
        Feature::for($this->gym)->deactivate('member-auth');
        $this->get($this->url('workouts'))->assertNotFound();
    }

    public function test_inactive_or_reset_portal_accounts_cannot_read_workouts(): void
    {
        $this->assignedWorkout();
        $this->login();
        $this->member->forceFill(['is_active' => false])->save();
        $this->get($this->url('workouts'))->assertRedirect($this->url('login'));
        $this->member->forceFill(['is_active' => true])->save();
        $this->login();
        $this->member->portal_password = Hash::make('new-password');
        $this->member->save();
        $this->get($this->url('workouts'))->assertRedirect($this->url('login'));
    }

    public function test_read_only_branch_keeps_existing_assigned_workout_visible(): void
    {
        $this->assignedWorkout();
        $this->gym->branches()->first()->forceFill(['is_active' => false])->save();
        $this->login();
        $this->get($this->url('workouts'))->assertInertia(fn (Assert $page) => $page->where('routine.name', 'Saved member workout'));
    }

    public function test_sign_in_shows_only_verified_membership_and_manila_dates(): void
    {
        $plan = MembershipPlan::factory()->create();
        (new MemberSubscription)->forceFill(['member_id' => $this->member->id, 'membership_plan_id' => $plan->id, 'plan_name' => 'Member monthly plan', 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000, 'starts_at' => '2026-10-05 16:00:00', 'ends_at' => '2026-11-04 16:00:00', 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(4, 0));
        $this->get($this->url('home'))->assertRedirect($this->url('login'));
        $this->retainCookie();
        $this->login();
        $this->assertGuest('web');
        $this->get($this->url('home'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertInertia(fn (Assert $page) => $page->component('portal/Membership')->where('memberName', $this->member->name)->where('membership.planName', 'Member monthly plan')->where('membership.validThrough', 'Nov 4, 2026')->where('auth.user', null)->where('currentGym', null)->missing('member.mobile')->missing('portal_password'));
        $this->get('http://localhost/dashboard')->assertRedirect('http://localhost/login');
    }

    public function test_public_login_and_qr_never_expose_member_details_or_staff_props(): void
    {
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
        $this->get($this->url('login'))->assertInertia(fn (Assert $page) => $page->where('auth.user', null)->where('currentGym', null)->missing('memberName')->missing('developmentAccounts'));
        [$raw] = $this->token();
        $this->postJson($this->url('resolve'), ['token' => $raw])->assertOk()->assertExactJson(['verified' => false, 'redirect' => $this->url('login')]);
        $this->retainCookie();
        $this->get($this->url('home'))->assertRedirect($this->url('login'));
    }

    public function test_qr_verification_requires_matching_password_and_revocation_ends_session(): void
    {
        [$raw, $token] = $this->token();
        $this->postJson($this->url('resolve'), ['token' => $raw])->assertOk();
        $this->retainCookie();
        $this->postJson($this->url('authenticate'), ['login' => 'test-member', 'password' => 'wrong'])->assertUnprocessable();
        $this->login();
        $this->get($this->url('home'))->assertOk();
        $token->revoked_at = now();
        $token->save();
        $this->get($this->url('home'))->assertRedirect($this->url('login'));
        $this->postJson($this->url('resolve'), ['token' => $raw])->assertUnprocessable();
    }

    public function test_another_member_cannot_verify_someone_elses_qr(): void
    {
        $other = Member::factory()->create();
        $other->forceFill(['portal_login' => 'other', 'portal_password' => Hash::make('test-password')])->save();
        [$raw] = $this->token();
        $this->postJson($this->url('resolve'), ['token' => $raw])->assertOk();
        $this->retainCookie();
        $this->postJson($this->url('authenticate'), ['login' => 'other', 'password' => 'test-password'])->assertUnprocessable();
        $this->get($this->url('home'))->assertRedirect($this->url('login'));
    }

    public function test_attendance_expired_and_foreign_qr_are_rejected(): void
    {
        [$attendance] = $this->token(MemberQrPurpose::Attendance);
        $this->postJson($this->url('resolve'), ['token' => $attendance])->assertUnprocessable();
        [$raw, $token] = $this->token();
        $token->expires_at = now()->subMinute();
        $token->save();
        $this->postJson($this->url('resolve'), ['token' => $raw])->assertUnprocessable();
        [$raw] = $this->token();
        $other = Gym::factory()->create();
        Feature::for($other)->activate('member-auth');
        $this->postJson(route('portal.resolve', ['gym' => $other->slug]), ['token' => $raw])->assertUnprocessable();
        $this->postJson($this->url('resolve'), ['token' => ['invalid']])->assertUnprocessable();
    }

    public function test_password_reset_inactivity_expiry_and_logout_clear_access(): void
    {
        $this->login();
        $this->member->portal_password = Hash::make('changed');
        $this->member->save();
        $this->get($this->url('home'))->assertRedirect($this->url('login'));
        $this->member->portal_password = Hash::make('test-password');
        $this->member->save();
        $this->login();
        $this->travel(31)->minutes();
        $this->get($this->url('home'))->assertRedirect($this->url('login'));
        $this->travelBack();
        $this->login();
        $this->post($this->url('logout'))->assertRedirect($this->url('login'));
        $this->retainCookie();
        $this->get($this->url('home'))->assertRedirect($this->url('login'));
        $this->member->is_active = false;
        $this->member->save();
        $this->postJson($this->url('authenticate'), ['login' => 'test-member', 'password' => 'test-password'])->assertUnprocessable();
    }

    public function test_other_gym_and_disabled_portal_cannot_read_membership(): void
    {
        $this->login();
        $other = Gym::factory()->create();
        Feature::for($other)->activate('member-auth');
        $this->get(route('portal.home', ['gym' => $other->slug]))->assertRedirect(route('portal.login', ['gym' => $other->slug]));
        Feature::for($this->gym)->deactivate('member-auth');
        $this->get($this->url('login'))->assertNotFound();
        $this->get($this->url('home'))->assertNotFound();
    }

    public function test_staff_management_secrets_are_one_time_hashed_audited_and_qr_purposes_separate(): void
    {
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Staff)->create());
        $member = Member::factory()->create();
        $endpoint = route('member-portal.store', $member);
        $result = $this->postJson($endpoint, ['operation' => 'create-login', 'login' => 'NEW-LOGIN'])->assertOk()->json();
        $this->assertSame('new-login', $result['credentials']['login']);
        $this->assertTrue(Hash::check($result['credentials']['password'], $member->refresh()->portal_password));
        $this->assertStringNotContainsString($result['credentials']['password'], AuditLog::sole()->toJson());
        $this->get(route('member-portal.show', $member))->assertInertia(fn (Assert $page) => $page->missing('credentials')->missing('payload')->missing('member.portal_password'));
        $payload = $this->postJson($endpoint, ['operation' => 'generate-qr'])->assertOk()->json('payload');
        $raw = parse_url($payload, PHP_URL_FRAGMENT);
        $this->assertSame(hash('sha256', $raw), MemberQrToken::sole()->token_hash);
        $this->postJson($endpoint, ['operation' => 'generate-qr'])->assertUnprocessable();
        $this->postJson($endpoint, ['operation' => 'reissue-qr'])->assertOk();
        $this->assertSame(1, MemberQrToken::usable(MemberQrPurpose::Portal)->count());
        $this->postJson($endpoint, ['operation' => 'disable-login'])->assertOk();
        $this->assertNull($member->refresh()->portal_password);
        $this->assertNull($member->portal_login);
    }

    public function test_management_rejects_coach_read_only_branch_and_disabled_feature(): void
    {
        $owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Coach)->create());
        $this->get(route('member-portal.show', $this->member))->assertForbidden();
        $this->postJson(route('member-portal.store', $this->member), ['operation' => 'reset-password'])->assertForbidden();
        $this->actingAs($owner);
        $this->member->homeBranch->forceFill(['is_active' => false])->save();
        $this->postJson(route('member-portal.store', $this->member), ['operation' => 'reset-password'])->assertUnprocessable();
        Feature::for($this->gym)->deactivate('member-auth');
        $this->get(route('member-portal.show', $this->member))->assertNotFound();
    }

    public function test_login_is_rate_limited_and_malformed_login_validates(): void
    {
        $this->postJson($this->url('authenticate'), ['login' => ['bad'], 'password' => 'bad'])->assertUnprocessable();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->url('authenticate'), ['login' => 'test-member', 'password' => 'bad'])->assertUnprocessable();
        }
        $this->postJson($this->url('authenticate'), ['login' => 'test-member', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_manager_scope_and_unique_login_are_enforced(): void
    {
        $this->gym->subscription->update(['plan' => 'multi_branch']);
        $manager = User::factory()->memberOf($this->gym, GymRole::BranchManager)->create();
        $this->gym->users()->updateExistingPivot($manager->id, ['branch_id' => $this->gym->mainBranch()->id]);
        $other = Member::factory()->create(['home_branch_id' => Branch::factory()->create()->id]);
        $this->actingAs($manager);
        $this->postJson(route('member-portal.store', $this->member), ['operation' => 'reset-password'])->assertOk();
        $this->get(route('member-portal.show', $other))->assertNotFound();
        $this->postJson(route('member-portal.store', $other), ['operation' => 'create-login', 'login' => 'foreign'])->assertNotFound();
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
        $this->postJson(route('member-portal.store', $other), ['operation' => 'create-login', 'login' => 'TEST-MEMBER'])->assertUnprocessable();
    }

    public function test_failed_audit_rolls_back_credentials(): void
    {
        $this->actingAs(User::factory()->memberOf($this->gym, GymRole::Owner)->create());
        $original = $this->member->portal_password;
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('member-portal.store', $this->member), ['operation' => 'reset-password']);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
            $this->assertSame($original, $this->member->refresh()->portal_password);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_expired_memberships_can_sign_in_and_pending_qr_expires(): void
    {
        $plan = MembershipPlan::factory()->create();
        (new MemberSubscription)->forceFill(['member_id' => $this->member->id, 'membership_plan_id' => $plan->id, 'plan_name' => 'Expired sample', 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 100, 'starts_at' => now()->subDays(31), 'ends_at' => now()->subDay(), 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();
        $this->login();
        $this->get($this->url('home'))->assertInertia(fn (Assert $page) => $page->where('membership.status', 'Expired')->has('renewalInstructions'));
        [$raw] = $this->token();
        $this->postJson($this->url('resolve'), ['token' => $raw])->assertOk();
        $this->retainCookie();
        $this->travel(11)->minutes();
        $this->postJson($this->url('authenticate'), ['login' => 'test-member', 'password' => 'test-password'])->assertUnprocessable();
    }
}
