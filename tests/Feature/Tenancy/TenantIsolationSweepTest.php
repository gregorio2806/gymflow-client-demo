<?php

namespace Tests\Feature\Tenancy;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Http\Middleware\SetCurrentGym;
use App\Models\AttendanceRecord;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Models\GymWebsite;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\PricingDiscount;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\User;
use App\Models\WebsiteInquiry;
use App\Models\WorkoutRoutine;
use App\Support\Audit;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gym B's users must never see or change Gym A's data, on any gym route.
 *
 * When you add a route inside the `has-gym` group, add its name to ISOLATION_CASES
 * and write a test below proving Gym B can't reach Gym A through it.
 */
class TenantIsolationSweepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every gym-scoped route name → the test method that covers it.
     */
    private const ISOLATION_CASES = [
        'freeze-records.index' => 'test_freeze_records_only_use_current_gym',
        'pricing.settings' => 'test_pricing_and_purchases_only_use_current_gym',
        'pricing.discounts.store' => 'test_pricing_and_purchases_only_use_current_gym',
        'pricing.discounts.update' => 'test_pricing_and_purchases_only_use_current_gym',
        'pricing.quote' => 'test_pricing_and_purchases_only_use_current_gym',
        'purchases.index' => 'test_pricing_and_purchases_only_use_current_gym',
        'purchases.store' => 'test_pricing_and_purchases_only_use_current_gym',
        'purchases.change' => 'test_pricing_and_purchases_only_use_current_gym',
        'branch-view.update' => 'test_branch_view_rejects_foreign_branches',
        'coach-calendar.availability' => 'test_coach_calendar_only_uses_current_gym',
        'coach-calendar.roster' => 'test_coach_calendar_only_uses_current_gym',
        'appointments.index' => 'test_coach_calendar_only_uses_current_gym',
        'appointments.store' => 'test_coach_calendar_only_uses_current_gym',
        'appointments.members' => 'test_coach_calendar_only_uses_current_gym',
        'appointments.slots' => 'test_coach_calendar_only_uses_current_gym',
        'appointments.update' => 'test_foreign_coach_schedules_cannot_be_managed',
        'appointments.status' => 'test_foreign_coach_schedules_cannot_be_managed',
        'coach-calendar.index' => 'test_coach_calendar_only_uses_current_gym',
        'coach-calendar.store' => 'test_coach_calendar_only_uses_current_gym',
        'coach-calendar.update' => 'test_foreign_coach_schedules_cannot_be_managed',
        'coach-calendar.cancel' => 'test_foreign_coach_schedules_cannot_be_managed',
        'reports.index' => 'test_reports_only_use_current_gym',
        'reports.export' => 'test_reports_only_use_current_gym',
        'reports.download' => 'test_reports_only_use_current_gym',
        'workout-routines.index' => 'test_workout_routines_only_use_current_gym',
        'workout-routines.create' => 'test_workout_routines_only_use_current_gym',
        'workout-routines.store' => 'test_workout_routines_only_use_current_gym',
        'workout-routines.edit' => 'test_workout_routines_only_use_current_gym',
        'workout-routines.update' => 'test_workout_routines_only_use_current_gym',
        'workout-routines.assign' => 'test_workout_routines_only_use_current_gym',
        'website.inquiries' => 'test_inquiry_inbox_only_uses_current_gym',
        'member-portal.show' => 'test_foreign_member_portal_cannot_be_managed',
        'member-portal.store' => 'test_foreign_member_portal_cannot_be_managed',
        'website.edit' => 'test_website_editor_only_uses_current_gym',
        'website.update' => 'test_website_editor_only_uses_current_gym',
        'website.preview' => 'test_website_editor_only_uses_current_gym',
        'website.publish' => 'test_website_editor_only_uses_current_gym',
        'website.image' => 'test_website_editor_only_uses_current_gym',
        'dashboard' => 'test_dashboard_shows_only_your_own_gym_even_with_a_tampered_session',
        'gym.settings.edit' => 'test_settings_page_shows_only_your_own_gym',
        'gym.settings.update' => 'test_settings_update_only_changes_your_own_gym',
        'staff.index' => 'test_staff_page_lists_only_your_own_team_and_invitations',
        'staff.invitations.store' => 'test_invites_are_created_for_your_own_gym_only',
        'staff.invitations.destroy' => 'test_another_gyms_invitation_cannot_be_cancelled',
        'staff.update' => 'test_another_gyms_team_member_cannot_be_edited',
        'branches.index' => 'test_branches_list_only_your_own_gym',
        'branches.store' => 'test_branches_are_created_only_for_your_own_gym',
        'branches.update' => 'test_another_gyms_branch_cannot_be_updated',
        'staff.activation' => 'test_another_gyms_staff_activation_cannot_be_changed',
        'branches.activation' => 'test_another_gyms_branch_activation_cannot_be_changed',
        'branches.selection' => 'test_active_branch_selection_rejects_other_gyms_branches',
        'product-inventory.index' => 'test_product_inventory_only_uses_current_gym',
        'product-inventory.store' => 'test_product_inventory_only_uses_current_gym',
        'product-inventory.edit' => 'test_product_inventory_only_uses_current_gym',
        'product-inventory.show' => 'test_product_inventory_only_uses_current_gym',
        'product-inventory.update' => 'test_product_inventory_only_uses_current_gym',
        'product-inventory.archive' => 'test_product_inventory_only_uses_current_gym',
        'product-inventory.stock' => 'test_product_inventory_only_uses_current_gym',
        'plan-selection.confirm' => 'test_selection_confirmation_only_changes_your_own_gym',
        'members.index' => 'test_member_pages_only_show_current_gym_data',
        'members.create' => 'test_member_pages_only_show_current_gym_data',
        'members.store' => 'test_members_are_created_only_in_the_current_gym',
        'members.edit' => 'test_other_gym_members_cannot_be_accessed_or_changed',
        'members.show' => 'test_other_gym_members_cannot_be_accessed_or_changed',
        'members.update' => 'test_other_gym_members_cannot_be_accessed_or_changed',
        'members.activation' => 'test_other_gym_members_cannot_be_accessed_or_changed',
        'membership-plans.index' => 'test_membership_plan_pages_only_show_current_gym',
        'membership-plans.create' => 'test_membership_plan_pages_only_show_current_gym',
        'membership-plans.store' => 'test_membership_plans_are_created_in_current_gym_only',
        'membership-plans.show' => 'test_other_gym_membership_plans_cannot_be_accessed_or_changed',
        'membership-plans.edit' => 'test_other_gym_membership_plans_cannot_be_accessed_or_changed',
        'membership-plans.update' => 'test_other_gym_membership_plans_cannot_be_accessed_or_changed',
        'membership-plans.activation' => 'test_other_gym_membership_plans_cannot_be_accessed_or_changed',
        'member-subscriptions.index' => 'test_other_gym_memberships_cannot_be_read_or_written',
        'member-subscriptions.store' => 'test_other_gym_memberships_cannot_be_read_or_written',
        'member-subscriptions.update' => 'test_other_gym_memberships_cannot_be_read_or_written',
        'member-qr.show' => 'test_other_gym_qr_cards_cannot_be_read_or_changed',
        'member-qr.store' => 'test_other_gym_qr_cards_cannot_be_read_or_changed',
        'check-in.index' => 'test_check_in_only_uses_current_gym_members_and_branches',
        'check-in.search' => 'test_check_in_only_uses_current_gym_members_and_branches',
        'check-in.store' => 'test_check_in_only_uses_current_gym_members_and_branches',
        'attendance.index' => 'test_attendance_history_only_shows_current_gym',
        'payments.create' => 'test_foreign_members_cannot_have_payments_read_or_recorded',
        'payments.store' => 'test_foreign_members_cannot_have_payments_read_or_recorded',
        'payments.index' => 'test_foreign_members_cannot_have_payments_read_or_recorded',
        'payments.void' => 'test_foreign_payments_cannot_be_voided',
        'payments.overview' => 'test_payment_overview_only_shows_current_gym',
        'payments.show' => 'test_foreign_payments_cannot_be_voided',
    ];

    private Gym $gymA;

    public function test_freeze_records_only_use_current_gym(): void
    {
        $member = app(CurrentGym::class)->runAs($this->gymA, function () {
            $member = Member::factory()->create();
            $plan = MembershipPlan::factory()->create();
            $period = new MemberSubscription;
            $period->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => $plan->name, 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 10000, 'starts_at' => now(), 'ends_at' => now()->addDays(30), 'status' => 'suspended', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();
            Audit::log('membership.suspended', $period);

            return $member;
        });
        $this->asOwnerBTargetingGymA()->get(route('freeze-records.index'))->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->asOwnerBTargetingGymA()->get(route('freeze-records.index', ['member_id' => $member->id]))->assertNotFound();
    }

    public function test_pricing_and_purchases_only_use_current_gym(): void
    {
        [$member, $discount, $purchase, $plan] = app(CurrentGym::class)->runAs($this->gymA, function () {
            $member = Member::factory()->create();
            $discount = (new PricingDiscount)->forceFill(['name' => 'Private A discount', 'category' => 'student', 'product_kinds' => ['subscription']]);
            $discount->save();
            $plan = MembershipPlan::factory()->create();
            $purchase = (new MemberPurchase)->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'branch_id' => $member->home_branch_id, 'kind' => 'benefit', 'plan_name' => 'Private A purchase', 'price_centavos' => 59900, 'original_price_centavos' => 59900, 'starts_at' => now(), 'ends_at' => now()->addYear(), 'snapshot' => [], 'request_key' => (string) Str::uuid()]);
            $purchase->save();

            return [$member, $discount, $purchase, $plan];
        });
        $this->asOwnerBTargetingGymA()->get(route('membership-plans.index'))->assertInertia(fn (Assert $p) => $p->has('discountRules', 0));
        $this->get(route('purchases.index', $member))->assertNotFound();
        $this->post(route('purchases.store', $member), [])->assertNotFound();
        $this->patch(route('purchases.change', [$member, $purchase]), [])->assertNotFound();
        $this->patch(route('pricing.discounts.update', $discount), [])->assertNotFound();
        $cart = ['starts_on' => '2026-10-08', 'request_key' => (string) Str::uuid()];
        $this->postJson(route('pricing.quote'), [...$cart, 'membership_plan_id' => $plan->id])->assertUnprocessable();
        $this->postJson(route('pricing.quote'), [...$cart, 'member_id' => $member->id])->assertNotFound();
        $this->patch(route('pricing.settings'), ['require_purchase' => false, 'packages_require_access' => false, 'promotion_stacking' => 'none', 'tax_profile' => 'non-vat', 'gym_id' => $this->gymA->id])->assertSessionHasNoErrors();
        $this->assertNull($this->gymA->refresh()->pricing_settings);
        $this->post(route('pricing.discounts.store'), ['name' => 'B discount', 'category' => 'student', 'unit' => 'percentage', 'input' => '5', 'requires_verification' => true, 'is_active' => true, 'product_kinds' => ['subscription'], 'gym_id' => $this->gymA->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pricing_discounts', ['name' => 'B discount', 'gym_id' => $this->gymB->id]);
    }

    public function test_branch_view_rejects_foreign_branches(): void
    {
        $this->asOwnerBTargetingGymA()->put(route('branch-view.update'), ['branch_id' => $this->gymA->mainBranch()->id, 'return_to' => '/dashboard'])->assertNotFound();
    }

    public function test_foreign_coach_schedules_cannot_be_managed(): void
    {
        $coach = User::factory()->memberOf($this->gymA, GymRole::Coach)->create();
        $schedule = app(CurrentGym::class)->runAs($this->gymA, function () use ($coach) {
            $schedule = (new CoachSchedule)->forceFill(['coach_id' => $coach->id, 'branch_id' => $this->gymA->mainBranch()->id, 'title' => 'Private coach A schedule', 'starts_at' => now()->utc(), 'ends_at' => now()->utc()->addHour()]);
            $schedule->save();

            return $schedule;
        });
        $this->asOwnerBTargetingGymA()->patch(route('coach-calendar.update', $schedule), [])->assertNotFound();
        $this->post(route('coach-calendar.cancel', $schedule), [])->assertNotFound();
        $this->patch(route('appointments.update', $schedule), [])->assertNotFound();
        $this->patch(route('appointments.status', $schedule), [])->assertNotFound();
        $this->assertDatabaseHas('coach_schedules', ['id' => $schedule->id, 'title' => 'Private coach A schedule', 'revision' => 1, 'is_cancelled' => false]);
    }

    public function test_coach_calendar_only_uses_current_gym(): void
    {
        $coach = User::factory()->memberOf($this->gymA, GymRole::Coach)->create(['name' => 'Private coach A']);
        $branchId = app(CurrentGym::class)->runAs($this->gymA, fn () => $this->gymA->mainBranch()->id);
        $this->asOwnerBTargetingGymA()->get(route('coach-calendar.roster'))->assertInertia(fn (Assert $page) => $page->has('coaches.data', 0));
        $this->asOwnerBTargetingGymA()->get(route('coach-calendar.index'))->assertInertia(fn (Assert $page) => $page->has('coaches.data', 0));
        $this->get(route('coach-calendar.index', ['coach_id' => $coach->id]))->assertNotFound();
        $this->post(route('coach-calendar.store'), ['coach_id' => $coach->id, 'branch_id' => $branchId, 'title' => 'Foreign schedule', 'starts_at' => '2026-10-06T09:00', 'ends_at' => '2026-10-06T10:00', 'request_key' => (string) Str::uuid(), 'month' => '2026-10', 'day' => '2026-10-06'])->assertNotFound();
        $this->get(route('appointments.index'))->assertInertia(fn (Assert $p) => $p->has('appointments.data', 0)->has('coaches.data', 0));
        $this->get(route('appointments.index', ['coach_id' => $coach->id]))->assertNotFound();
        $this->put(route('coach-calendar.availability', $coach->id), ['revision' => 1, 'hours' => array_fill(0, 7, ['enabled' => true, 'start' => '09:00', 'end' => '18:00'])])->assertNotFound();
        $this->post(route('appointments.store'), ['coach_id' => $coach->id, 'branch_id' => $branchId, 'customer_name' => 'Foreign customer', 'service' => 'consultation', 'starts_at' => '2026-10-08T09:00', 'ends_at' => '2026-10-08T10:00', 'request_key' => (string) Str::uuid()])->assertNotFound();
        $this->getJson(route('appointments.slots', ['coach_id' => $coach->id, 'branch_id' => $branchId, 'date' => '2026-10-08', 'duration' => 60]))->assertForbidden();
        $this->getJson(route('appointments.members', ['coach_id' => $coach->id, 'branch_id' => $branchId]))->assertForbidden();
        $this->assertDatabaseCount('coach_schedules', 0);
    }

    public function test_reports_only_use_current_gym(): void
    {
        $owner = User::where('email', 'owner-a@example.com')->firstOrFail();
        $export = app(CurrentGym::class)->runAs($this->gymA, function () use ($owner) {
            $row = (new ReportExport)->forceFill(['user_id' => $owner->id, 'requested_role' => 'owner', 'from' => '2026-10-01', 'through' => '2026-10-01', 'request_key' => (string) Str::uuid(), 'expires_at' => now()->addDay(), 'status' => 'ready', 'csv' => 'Private tenant A']);
            $row->save();

            return $row;
        });
        $this->gymB->subscription->update(['plan' => Plan::Pro]);
        $this->asOwnerBTargetingGymA()->get(route('reports.index'))->assertInertia(fn (Assert $p) => $p->has('exports.data', 0)->where('summary.checkIns', 0));
        $this->get(route('reports.download', $export))->assertNotFound();
        $this->post(route('reports.export'), ['from' => '2026-10-01', 'through' => '2026-10-01', 'branch_id' => $this->gymA->mainBranch()->id, 'request_key' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_workout_routines_only_use_current_gym(): void
    {
        $data = ['name' => 'Private routine A', 'request_key' => (string) Str::uuid(), 'branch_id' => $this->gymA->branches()->first()->id, 'days' => [['name' => 'Day 1', 'exercises' => [['name' => 'Squat', 'sets' => 3, 'reps' => '10', 'notes' => '']]]]];
        $routine = app(CurrentGym::class)->runAs($this->gymA, fn () => (new WorkoutRoutine)->forceFill([...$data, 'created_by' => null, 'revision' => 1]));
        app(CurrentGym::class)->runAs($this->gymA, fn () => $routine->save());
        $this->asOwnerBTargetingGymA()->get(route('workout-routines.index'))->assertInertia(fn (Assert $page) => $page->has('routines.data', 0));
        $this->get(route('workout-routines.create'))->assertInertia(fn (Assert $page) => $page->where('branches.data.0.id', $this->gymB->branches()->first()->id));
        $this->get(route('workout-routines.edit', $routine))->assertNotFound();
        $this->patch(route('workout-routines.update', $routine), [...$data, 'revision' => 1])->assertNotFound();
        $this->post(route('workout-routines.assign', $routine), ['member_id' => 1, 'revision' => 1])->assertNotFound();
        $this->post(route('workout-routines.store'), $data)->assertNotFound();
        $this->post(route('workout-routines.store'), [...$data, 'branch_id' => $this->gymB->branches()->first()->id, 'gym_id' => $this->gymA->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_routines', ['gym_id' => $this->gymB->id, 'request_key' => $data['request_key']]);
    }

    public function test_inquiry_inbox_only_uses_current_gym(): void
    {
        app(CurrentGym::class)->runAs($this->gymA, fn () => (new WebsiteInquiry)->forceFill(['name' => 'Private visitor A', 'email' => 'private@example.invalid', 'message' => 'Private message A', 'request_hash' => str_repeat('a', 64), 'content_hash' => str_repeat('b', 64), 'privacy_version' => '2026-10-06', 'privacy_revision' => str_repeat('c', 64), 'consented_at' => now()])->save());
        $this->asOwnerBTargetingGymA()->get(route('website.inquiries'))->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 0));
    }

    public function test_foreign_member_portal_cannot_be_managed(): void
    {
        $member = app(CurrentGym::class)->runAs($this->gymA, fn () => Member::factory()->create());
        $this->asOwnerBTargetingGymA()->get(route('member-portal.show', $member))->assertNotFound();
        $this->asOwnerBTargetingGymA()->postJson(route('member-portal.store', $member), ['operation' => 'create-login', 'login' => 'foreign'])->assertNotFound();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'portal_password' => null]);
    }

    public function test_website_editor_only_uses_current_gym(): void
    {
        app(CurrentGym::class)->runAs($this->gymA, fn () => (new GymWebsite)->forceFill(['draft_content' => ['description' => 'Private draft A'], 'draft_revision' => 1])->save());
        $this->asOwnerBTargetingGymA()->get(route('website.edit'))->assertInertia(fn (Assert $page) => $page->where('draft.description', '')->where('revision', 0));
        $this->asOwnerBTargetingGymA()->get(route('website.preview'))->assertNotFound();
        $this->asOwnerBTargetingGymA()->get(route('website.image'))->assertNotFound();
        $this->asOwnerBTargetingGymA()->post(route('website.publish'), ['revision' => 1])->assertNotFound();
        $this->asOwnerBTargetingGymA()->post(route('website.update'), ['gym_id' => $this->gymA->id, 'revision' => 0, 'description' => 'Own draft B', 'contact' => ['phone' => null], 'plans' => [], 'facilities' => [], 'announcements' => []])->assertRedirect();
        $this->assertDatabaseHas('gym_websites', ['gym_id' => $this->gymA->id, 'draft_revision' => 1]);
        app(CurrentGym::class)->runAs($this->gymA, fn () => $this->assertSame('Private draft A', GymWebsite::sole()->draft_content['description']));
        app(CurrentGym::class)->runAs($this->gymB, fn () => $this->assertSame('Own draft B', GymWebsite::sole()->draft_content['description']));
    }

    public function test_payment_overview_only_shows_current_gym(): void
    {
        $this->asOwnerBTargetingGymA()->get(route('payments.overview'))->assertInertia(fn (Assert $page) => $page->has('payments.data', 0)->has('balances.data', 0)->where('summary.revenueCentavos', 0));
        $this->asOwnerBTargetingGymA()->get(route('payments.overview', ['branch_id' => $this->gymA->mainBranch()->id]))->assertNotFound();
    }

    public function test_foreign_members_cannot_have_payments_read_or_recorded(): void
    {
        $member = app(CurrentGym::class)->runAs($this->gymA, fn () => Member::factory()->create());
        $this->asOwnerBTargetingGymA()->get(route('payments.create', $member))->assertNotFound();
        $this->asOwnerBTargetingGymA()->get(route('payments.index', $member))->assertNotFound();
        $this->asOwnerBTargetingGymA()->post(route('payments.store', $member), [])->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_foreign_payments_cannot_be_voided(): void
    {
        $payment = app(CurrentGym::class)->runAs($this->gymA, function () {
            $member = Member::factory()->create();
            $plan = MembershipPlan::factory()->create();
            $period = new MemberSubscription;
            $period->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => $plan->name, 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 10000, 'starts_at' => now(), 'ends_at' => now()->addDays(30), 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();
            $payment = new Payment;
            $payment->forceFill(['member_id' => $member->id, 'member_subscription_id' => $period->id, 'branch_id' => $member->home_branch_id, 'amount_centavos' => 10000, 'method' => 'cash', 'recorded_by' => $this->gymA->users()->first()->id, 'paid_at' => now(), 'request_key' => (string) Str::uuid()])->save();

            return $payment;
        });
        $this->asOwnerBTargetingGymA()->patch(route('payments.void', $payment), ['void_reason' => 'Foreign attempt'])->assertNotFound();
        $this->asOwnerBTargetingGymA()->get(route('payments.show', $payment))->assertNotFound();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'recorded']);
    }

    public function test_attendance_history_only_shows_current_gym(): void
    {
        app(CurrentGym::class)->runAs($this->gymA, function () {
            $record = new AttendanceRecord;
            $record->forceFill(['member_id' => Member::factory()->create()->id, 'branch_id' => $this->gymA->mainBranch()->id, 'source' => 'manual', 'checked_in_at' => now()])->save();
        });
        $this->asOwnerBTargetingGymA()->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page->has('attendance.data', 0)->where('summary.checkIns', 0)->where('summary.uniqueMembers', 0));
        $this->asOwnerBTargetingGymA()->get(route('attendance.index', ['branch_id' => $this->gymA->mainBranch()->id]))->assertNotFound();
    }

    public function test_check_in_only_uses_current_gym_members_and_branches(): void
    {
        $member = app(CurrentGym::class)->runAs($this->gymA, fn () => Member::factory()->create(['name' => 'Private A']));
        $this->asOwnerBTargetingGymA()->get(route('check-in.index'))->assertInertia(fn (Assert $page) => $page->where('branches.0.id', $this->gymB->mainBranch()->id)->has('branches', 1));
        $this->asOwnerBTargetingGymA()->getJson(route('check-in.search', ['search' => 'Private']))->assertJsonPath('members.total', 0);
        $this->asOwnerBTargetingGymA()->postJson(route('check-in.store'), ['member_id' => $member->id, 'branch_id' => $this->gymB->mainBranch()->id])->assertNotFound();
        $ownMember = app(CurrentGym::class)->runAs($this->gymB, fn () => Member::factory()->create());
        $this->asOwnerBTargetingGymA()->postJson(route('check-in.store'), ['member_id' => $ownMember->id, 'branch_id' => $this->gymA->mainBranch()->id])->assertNotFound();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_other_gym_qr_cards_cannot_be_read_or_changed(): void
    {
        $member = app(CurrentGym::class)->runAs($this->gymA, fn () => Member::factory()->create());
        $this->asOwnerBTargetingGymA()->get(route('member-qr.show', $member))->assertNotFound();
        $this->asOwnerBTargetingGymA()->postJson(route('member-qr.store', $member), ['operation' => 'generate'])->assertNotFound();
        $this->assertDatabaseCount('member_qr_tokens', 0);
    }

    public function test_other_gym_memberships_cannot_be_read_or_written(): void
    {
        $member = app(CurrentGym::class)->runAs($this->gymA, fn () => Member::factory()->create());
        $subscription = app(CurrentGym::class)->runAs($this->gymA, function () use ($member) {
            $plan = MembershipPlan::factory()->create();
            $subscription = new MemberSubscription;
            $subscription->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => $plan->name, 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 100, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(29), 'status' => 'active', 'operation' => 'assign', 'request_key' => (string) Str::uuid()])->save();

            return $subscription;
        });
        $ownMember = app(CurrentGym::class)->runAs($this->gymB, fn () => Member::factory()->create());
        $this->asOwnerBTargetingGymA()->get(route('member-subscriptions.index', $member))->assertNotFound();
        $this->asOwnerBTargetingGymA()->post(route('member-subscriptions.store', $member), [])->assertNotFound();
        $this->asOwnerBTargetingGymA()->patch(route('member-subscriptions.update', [$ownMember, $subscription]), ['action' => 'cancel'])->assertNotFound();
        $this->assertSame('active', $subscription->refresh()->status->value);
    }

    public function test_membership_plan_pages_only_show_current_gym(): void
    {
        app(CurrentGym::class)->runAs($this->gymA, fn () => MembershipPlan::factory()->create());
        $this->asOwnerBTargetingGymA()->get(route('membership-plans.index'))->assertInertia(fn (Assert $page) => $page->has('plans.data', 0));
        $this->asOwnerBTargetingGymA()->get(route('membership-plans.create'))->assertInertia(fn (Assert $page) => $page->where('plan', null)->where('currentGym.id', $this->gymB->id));
    }

    public function test_membership_plans_are_created_in_current_gym_only(): void
    {
        $this->asOwnerBTargetingGymA()->post(route('membership-plans.store'), ['name' => 'B only', 'duration_amount' => 1, 'duration_unit' => 'months', 'price_php' => '1500.50', 'gym_id' => $this->gymA->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_plans', ['name' => 'B only', 'gym_id' => $this->gymB->id, 'price_centavos' => 150050]);
    }

    public function test_other_gym_membership_plans_cannot_be_accessed_or_changed(): void
    {
        $plan = app(CurrentGym::class)->runAs($this->gymA, fn () => MembershipPlan::factory()->create());
        $this->asOwnerBTargetingGymA()->get(route('membership-plans.edit', $plan))->assertNotFound();
        $this->asOwnerBTargetingGymA()->get(route('membership-plans.show', $plan))->assertNotFound();
        $this->asOwnerBTargetingGymA()->patch(route('membership-plans.update', $plan), ['name' => 'Hacked'])->assertNotFound();
        $this->asOwnerBTargetingGymA()->patch(route('membership-plans.activation', $plan), ['is_active' => false])->assertNotFound();
        $this->assertTrue($plan->refresh()->is_active);
    }

    public function test_member_pages_only_show_current_gym_data(): void
    {
        app(CurrentGym::class)->runAs($this->gymA, fn () => Member::factory()->create(['name' => 'Private A']));
        $this->asOwnerBTargetingGymA()->get(route('members.index'))->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
        $this->asOwnerBTargetingGymA()->get(route('members.create'))->assertInertia(fn (Assert $page) => $page->has('branches', 1)->where('branches.0.id', $this->gymB->mainBranch()->id));
    }

    public function test_members_are_created_only_in_the_current_gym(): void
    {
        $plan = app(CurrentGym::class)->runAs($this->gymB, fn () => MembershipPlan::factory()->create());
        $this->asOwnerBTargetingGymA()->post(route('members.store'), ['name' => 'New B', 'mobile' => '09000000001', 'home_branch_id' => $this->gymA->mainBranch()->id, 'privacy_consent' => true, 'gym_id' => $this->gymA->id,
            'membership_plan_id' => $plan->id, 'starts_on' => now()->setTimezone($this->gymB->timezone)->toDateString(), 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('members', ['name' => 'New B', 'gym_id' => $this->gymB->id, 'home_branch_id' => $this->gymB->mainBranch()->id]);
        $this->assertDatabaseHas('member_subscriptions', ['gym_id' => $this->gymB->id, 'membership_plan_id' => $plan->id]);
    }

    public function test_other_gym_members_cannot_be_accessed_or_changed(): void
    {
        $member = app(CurrentGym::class)->runAs($this->gymA, fn () => Member::factory()->create());
        $this->asOwnerBTargetingGymA()->get(route('members.edit', $member))->assertNotFound();
        $this->asOwnerBTargetingGymA()->get(route('members.show', $member))->assertNotFound();
        $this->asOwnerBTargetingGymA()->patch(route('members.update', $member), ['name' => 'Changed'])->assertNotFound();
        $this->asOwnerBTargetingGymA()->patch(route('members.activation', $member), ['is_active' => false])->assertNotFound();
        $this->assertTrue($member->refresh()->is_active);
    }

    public function test_another_gyms_staff_activation_cannot_be_changed()
    {
        $staff = $this->gymA->users()->wherePivot('role', GymRole::Staff->value)->sole();
        $this->asOwnerBTargetingGymA()->patch(route('staff.activation', $staff), ['is_active' => false])->assertNotFound();
        $this->assertTrue($this->gymA->users()->whereKey($staff->id)->sole()->pivot->is_active);
    }

    public function test_another_gyms_branch_activation_cannot_be_changed()
    {
        $this->asOwnerBTargetingGymA()->patch(route('branches.activation', $this->gymA->mainBranch()), ['is_active' => false])->assertNotFound();
        $this->assertTrue($this->gymA->mainBranch()->is_active);
    }

    public function test_active_branch_selection_rejects_other_gyms_branches()
    {
        $this->asOwnerBTargetingGymA()->put(route('branches.selection'), ['branch_ids' => [$this->gymA->mainBranch()->id]])->assertSessionHasErrors('branch_ids.0');
        $this->assertTrue($this->gymA->mainBranch()->is_active);
    }

    public function test_product_inventory_only_uses_current_gym(): void
    {
        $product = app(CurrentGym::class)->runAs($this->gymA, function () {
            $product = new Product(['name' => 'Private A product', 'cost_centavos' => 1000, 'price_centavos' => 2000, 'status' => 'active']);
            $product->save();

            return $product;
        });

        $this->asOwnerBTargetingGymA()->get(route('product-inventory.index'))->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        $this->asOwnerBTargetingGymA()->post(route('product-inventory.store'), ['name' => 'B product', 'cost_php' => '10.00', 'price_php' => '20.00', 'low_stock_threshold' => 0, 'initial_branch_id' => $this->gymB->mainBranch()->id])->assertRedirect();
        $this->assertDatabaseHas('products', ['gym_id' => $this->gymB->id, 'name' => 'B product']);
        $this->asOwnerBTargetingGymA()->get(route('product-inventory.show', $product))->assertNotFound();
        $this->asOwnerBTargetingGymA()->get(route('product-inventory.edit', $product))->assertNotFound();
        $this->asOwnerBTargetingGymA()->patch(route('product-inventory.update', $product), [])->assertNotFound();
        $this->asOwnerBTargetingGymA()->patch(route('product-inventory.archive', $product), ['is_active' => false])->assertNotFound();
        $this->asOwnerBTargetingGymA()->post(route('product-inventory.stock', $product), ['branch_id' => $this->gymB->mainBranch()->id, 'type' => 'received', 'quantity' => 1])->assertNotFound();
        $this->assertSame('active', $product->refresh()->status);
    }

    public function test_selection_confirmation_only_changes_your_own_gym()
    {
        $this->gymA->forceFill(['needs_staff_selection' => true])->save();
        $this->gymB->forceFill(['needs_staff_selection' => true])->save();
        $this->asOwnerBTargetingGymA()->put(route('plan-selection.confirm'), ['selection' => 'staff'])->assertRedirect();
        $this->assertTrue($this->gymA->refresh()->needs_staff_selection);
        $this->assertFalse($this->gymB->refresh()->needs_staff_selection);
    }

    public function test_branches_list_only_your_own_gym()
    {
        $this->gymB->subscription()->update(['plan' => 'multi_branch']);
        $this->asOwnerBTargetingGymA()->get(route('branches.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('branches.data', 1)->where('branches.data.0.id', $this->gymB->mainBranch()->id));
    }

    public function test_branches_are_created_only_for_your_own_gym()
    {
        $this->gymB->subscription()->update(['plan' => 'multi_branch']);
        $this->asOwnerBTargetingGymA()->post(route('branches.store'), ['name' => 'New location', 'gym_id' => $this->gymA->id, 'is_main' => true])->assertRedirect();
        $this->assertSame(1, $this->gymA->branches()->count());
        $this->assertDatabaseHas('branches', ['name' => 'New location', 'gym_id' => $this->gymB->id, 'is_main' => false]);
    }

    public function test_another_gyms_branch_cannot_be_updated()
    {
        $this->gymB->subscription()->update(['plan' => 'multi_branch']);
        $this->asOwnerBTargetingGymA()->patch(route('branches.update', $this->gymA->mainBranch()), ['name' => 'Hacked'])->assertNotFound();
        $this->assertSame('Main branch', $this->gymA->mainBranch()->name);
    }

    private Gym $gymB;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->gymA = Gym::factory()->create(['name' => 'Gym A']);
        $this->gymB = Gym::factory()->create(['name' => 'Gym B']);
        User::factory()->memberOf($this->gymA, GymRole::Owner)->create(['email' => 'owner-a@example.com']);
        $this->ownerB = User::factory()->memberOf($this->gymB, GymRole::Owner)->create(['email' => 'owner-b@example.com']);
        User::factory()->memberOf($this->gymA, GymRole::Staff)->create(['email' => 'staff-a@example.com']);
        GymInvitation::factory()->create(['gym_id' => $this->gymA->id, 'email' => 'invitee-a@example.com']);
    }

    /**
     * Gym B owner, with a session that (maliciously) points at Gym A.
     */
    private function asOwnerBTargetingGymA(): static
    {
        return $this->actingAs($this->ownerB)->withSession([SetCurrentGym::SESSION_KEY => $this->gymA->id]);
    }

    public function test_every_gym_route_has_an_isolation_case()
    {
        $gymRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('has-gym', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->getName())
            ->values();

        $missing = $gymRoutes->diff(array_keys(self::ISOLATION_CASES));
        $stale = collect(array_keys(self::ISOLATION_CASES))->diff($gymRoutes);

        $this->assertNotEmpty($gymRoutes, 'No has-gym routes found — the route filter is broken.');
        $this->assertEmpty($missing, 'Add a cross-tenant test to ISOLATION_CASES for: '.$missing->implode(', '));
        $this->assertEmpty($stale, 'ISOLATION_CASES lists routes that are not has-gym routes: '.$stale->implode(', '));

        foreach (self::ISOLATION_CASES as $method) {
            $this->assertTrue(method_exists($this, $method), "Missing test method {$method}");
        }
    }

    public function test_dashboard_shows_only_your_own_gym_even_with_a_tampered_session()
    {
        $this->asOwnerBTargetingGymA()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('currentGym.id', $this->gymB->id));
    }

    public function test_settings_page_shows_only_your_own_gym()
    {
        $this->asOwnerBTargetingGymA()
            ->get(route('gym.settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('gym.name', 'Gym B'));
    }

    public function test_settings_update_only_changes_your_own_gym()
    {
        $this->asOwnerBTargetingGymA()
            ->patch(route('gym.settings.update'), ['name' => 'Hacked', 'timezone' => 'Asia/Manila']);

        $this->assertSame('Gym A', $this->gymA->refresh()->name);
        $this->assertSame('Hacked', $this->gymB->refresh()->name);
    }

    public function test_staff_page_lists_only_your_own_team_and_invitations()
    {
        $this->asOwnerBTargetingGymA()
            ->get(route('staff.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('members', 1)
                ->where('members.0.email', 'owner-b@example.com')
                ->has('invitations', 0));
    }

    public function test_invites_are_created_for_your_own_gym_only()
    {
        $this->asOwnerBTargetingGymA()
            ->post(route('staff.invitations.store'), ['email' => 'new@example.com', 'role' => 'staff', 'gym_id' => $this->gymA->id]);

        $invitation = GymInvitation::withoutGlobalScope('gym')->where('email', 'new@example.com')->sole();
        $this->assertSame($this->gymB->id, $invitation->gym_id);
    }

    public function test_another_gyms_team_member_cannot_be_edited()
    {
        $staffOfA = User::where('email', 'staff-a@example.com')->sole();

        $this->asOwnerBTargetingGymA()
            ->patch(route('staff.update', $staffOfA->id), ['name' => 'Hacked', 'role' => 'coach'])
            ->assertNotFound();

        $this->assertNotSame('Hacked', $staffOfA->refresh()->name);
        $this->assertSame(GymRole::Staff, $staffOfA->roleIn($this->gymA));
    }

    public function test_another_gyms_invitation_cannot_be_cancelled()
    {
        $invitationOfA = GymInvitation::withoutGlobalScope('gym')->where('email', 'invitee-a@example.com')->sole();

        $this->asOwnerBTargetingGymA()
            ->delete(route('staff.invitations.destroy', $invitationOfA->id))
            ->assertNotFound();

        $this->assertDatabaseHas('gym_invitations', ['id' => $invitationOfA->id]);
    }
}
