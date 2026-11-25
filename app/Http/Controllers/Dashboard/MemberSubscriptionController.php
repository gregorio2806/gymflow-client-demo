<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\ManageMemberSubscription;
use App\Enums\MemberSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AssignMembershipRequest;
use App\Http\Requests\Dashboard\ChangeMembershipRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class MemberSubscriptionController extends Controller
{
    public function freezeRecords(Request $request): Response
    {
        Gate::authorize('viewAny', Member::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'member_id' => ['nullable', 'integer', 'min:1']]);
        $search = trim($filters['search'] ?? '');
        $member = isset($filters['member_id']) ? Member::findOrFail($filters['member_id']) : null;
        if ($member) {
            Gate::authorize('update', $member);
        }
        $gym = app(CurrentGym::class)->get();
        $branch = app(CurrentGym::class)->viewBranchId();
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $subscriptions = MemberSubscription::when($member, fn ($q) => $q->where('member_id', $member->id))->whereHas('member', fn ($q) => $q->when($branch && ! $member, fn ($q) => $q->where('home_branch_id', $branch))->when($search !== '', fn ($q) => $q->where(fn ($match) => $match->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->when(ctype_digit($search), fn ($match) => $match->orWhere('id', (int) $search)))));
        $records = AuditLog::where('subject_type', (new MemberSubscription)->getMorphClass())->whereIn('subject_id', $subscriptions->select('id'))->whereIn('action', ['membership.suspended', 'membership.resumed'])->with(['subject.member:id,name', 'user:id,name'])->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('dashboard/members/FreezeRecords', ['member' => $member?->only(['id', 'name']), 'search' => $search, 'records' => $records->through(fn ($log) => [
            'id' => $log->id, 'memberId' => $log->subject->member_id, 'member' => $log->subject->member?->name ?? 'Removed member', 'plan' => $log->subject->plan_name, 'action' => $log->action === 'membership.suspended' ? 'Frozen' : 'Resumed', 'status' => $log->subject->displayStatus(null, $gym->timezone), 'validThrough' => $log->subject->ends_at->subSecond()->setTimezone($gym->timezone)->format('F j, Y'), 'createdAt' => $log->created_at->setTimezone($gym->timezone)->format('F j, Y g:i A'), 'createdBy' => $log->user?->name ?? 'Not recorded', 'reason' => $log->changes['reason'] ?? '',
        ])]);
    }

    public function index(Member $member): Response
    {
        Gate::authorize('update', $member);
        $timezone = app(CurrentGym::class)->get()->timezone;
        $canManage = Branch::whereKey($member->home_branch_id)->where('is_active', true)->exists();
        $member->load(['homeBranch', 'currentMembership', 'nextMembership', 'latestMembership']);
        $summary = $member->currentMembership ?? $member->nextMembership ?? $member->latestMembership;
        $hasAttendanceQr = $member->qrTokens()->where('purpose', 'attendance')->whereNull('revoked_at')->exists();
        $paymentController = app(PaymentController::class);
        $paymentsEnabled = Feature::active('payments');

        return Inertia::render('dashboard/members/Subscriptions', [
            'member' => [...$member->only(['id', 'name', 'mobile', 'email', 'is_active']), 'hasAttendanceQr' => $hasAttendanceQr,
                'branchName' => $member->homeBranch?->name,
                'hasConsent' => $member->privacy_consented_at !== null,
                'joinedOn' => $member->created_at->setTimezone($timezone)->toDateString(),
                'planName' => $summary?->plan_name,
                'planAmountCentavos' => $summary?->price_centavos,
                'planDuration' => $summary ? $summary->duration_amount.' '.$summary->duration_unit : null,
                'startsOn' => $summary?->starts_at->setTimezone($timezone)->toDateString(),
                'validThrough' => $summary?->ends_at->subSecond()->setTimezone($timezone)->toDateString(),
                'renewalDue' => $summary && $summary->status !== MemberSubscriptionStatus::Cancelled ? $summary->ends_at->setTimezone($timezone)->toDateString() : null,
                'membershipStatus' => ($member->currentMembership ?? $member->nextMembership ?? $member->latestMembership)?->displayStatus(null, $timezone) ?? 'No membership'],
            'readOnly' => true,
            'canSave' => $canManage,
            'branches' => [$member->homeBranch->only(['id', 'name'])],
            'canManage' => $canManage,
            'canRenew' => $member->subscriptions()->where('status', '!=', MemberSubscriptionStatus::Cancelled)->exists(),
            'paymentForm' => $paymentsEnabled ? $paymentController->formData($member, 'payment_memberships_page') : null,
            'paymentHistory' => $paymentsEnabled ? $paymentController->historyData($member, 'payments_page') : null,
            'today' => now()->setTimezone($timezone)->toDateString(),
            'plans' => MembershipPlan::where('is_active', true)->orderBy('name')->get()->map(fn ($plan) => $plan->only(['id', 'name', 'price_centavos', 'duration_amount', 'duration_unit'])),
            'subscriptions' => $member->subscriptions()->with('creationAudit.user:id,name')->orderByDesc('starts_at')->orderByDesc('id')->paginate(25)->through(fn ($subscription) => [
                ...$subscription->only(['id', 'plan_name', 'price_centavos']),
                'discountPercent' => $subscription->discount_basis_points / 100,
                'discountMode' => $subscription->discount_mode,
                'discountCentavos' => ($subscription->original_price_centavos ?? $subscription->price_centavos) - $subscription->price_centavos,
                'discountType' => ! empty($subscription->pricing_snapshot['discounts']) ? implode(', ', array_column($subscription->pricing_snapshot['discounts'], 'reason')) : ($subscription->discount_type === 'others' ? $subscription->discount_other : match ($subscription->discount_type) {
                    'pwd' => 'PWD', default => ucfirst($subscription->discount_type),
                }),
                'originalPriceCentavos' => $subscription->original_price_centavos,
                'createdAt' => $subscription->created_at->setTimezone($timezone)->format('M j, Y g:i A'),
                'createdBy' => $subscription->creationAudit?->user?->name ?? __('Not recorded'),
                'status' => $subscription->displayStatus(null, $timezone),
                'startsOn' => $subscription->starts_at->setTimezone($timezone)->toDateString(),
                'validThrough' => $subscription->ends_at->subSecond()->setTimezone($timezone)->toDateString(),
                'canSuspend' => $canManage && $subscription->status === MemberSubscriptionStatus::Active && $subscription->ends_at->isFuture(),
                'canResume' => $canManage && $subscription->status === MemberSubscriptionStatus::Suspended && $subscription->ends_at->isFuture(),
                'canCancel' => $canManage && $subscription->status !== MemberSubscriptionStatus::Cancelled,
            ]),
            'currentSubscriptions' => $member->subscriptions()->where('status', MemberSubscriptionStatus::Active)->where('ends_at', '>', now())->orderBy('starts_at')->limit(50)->get()->map(fn ($subscription) => [
                'id' => $subscription->id,
                'planName' => $subscription->plan_name,
                'startsOn' => $subscription->starts_at->setTimezone($timezone)->toDateString(),
                'validThrough' => $subscription->ends_at->subSecond()->setTimezone($timezone)->toDateString(),
                'status' => $subscription->displayStatus(null, $timezone),
            ]),
            'membershipPlans' => MemberPurchase::where('member_id', $member->id)->where('kind', 'benefit')->latest('id')->paginate(25, ['id', 'plan_name', 'price_centavos', 'starts_at', 'ends_at', 'status'], 'membership_plans_page')->through(fn ($purchase) => [
                'id' => $purchase->id,
                'planName' => $purchase->plan_name,
                'priceCentavos' => $purchase->price_centavos,
                'startsOn' => $purchase->starts_at->setTimezone($timezone)->toDateString(),
                'validThrough' => $purchase->ends_at?->subDay()->setTimezone($timezone)->toDateString(),
                'status' => $purchase->status === 'cancelled' ? 'Cancelled' : ($purchase->ends_at && $purchase->ends_at <= now() ? 'Expired' : ($purchase->starts_at > now() ? 'Scheduled' : 'Active')),
            ]),
            'currentMembershipPlans' => MemberPurchase::where('member_id', $member->id)->where('kind', 'benefit')->where('status', 'active')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderBy('starts_at')->limit(50)->get(['id', 'plan_name', 'price_centavos', 'starts_at', 'ends_at'])->map(fn ($purchase) => [
                'id' => $purchase->id,
                'planName' => $purchase->plan_name,
                'priceCentavos' => $purchase->price_centavos,
                'startsOn' => $purchase->starts_at->setTimezone($timezone)->toDateString(),
                'validThrough' => $purchase->ends_at?->subDay()->setTimezone($timezone)->toDateString(),
                'status' => $purchase->starts_at->isFuture() ? 'Scheduled' : 'Active',
            ]),
        ]);
    }

    public function store(AssignMembershipRequest $request, Member $member, ManageMemberSubscription $action): RedirectResponse
    {
        $action->assign($member, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership saved.')]);

        return back();
    }

    public function update(ChangeMembershipRequest $request, Member $member, MemberSubscription $subscription, ManageMemberSubscription $action): RedirectResponse
    {
        abort_unless($subscription->member_id === $member->id, 404);
        $action->change($member, $subscription, $request->validated('action'), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership updated.')]);

        return back();
    }
}
