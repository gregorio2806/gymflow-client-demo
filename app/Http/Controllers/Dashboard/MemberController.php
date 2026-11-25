<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\SaveMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\MemberActivationRequest;
use App\Http\Requests\Dashboard\SaveMemberRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Support\MemberSummaryFilter;
use App\Support\PricingCatalog;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Member::class);
        $canManage = Gate::allows('create', Member::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])], 'section' => ['nullable', Rule::in(['member-payment'])], 'plan_id' => ['nullable', 'integer'], 'membership_state' => ['nullable', Rule::in(['active', 'expiring-soon', 'expired', 'scheduled', 'suspended', 'cancelled', 'none'])], 'per_page' => ['nullable', Rule::in([8, 25, 50])]]);
        $paymentSelection = ($filters['section'] ?? null) === 'member-payment';
        if ($paymentSelection) {
            abort_unless(Feature::active('payments'), 404);
            Gate::authorize('viewAny', Payment::class);
        }
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $timezone = app(CurrentGym::class)->get()->timezone;
        $base = Member::query();
        $profileCounts = (clone $base)->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as inactive')->first();
        $active = (clone $base)->whereHas('currentMembership', fn ($q) => $q->where('status', 'active'));
        $cutoff = CarbonImmutable::now($timezone)->startOfDay()->addDays(max(0, (int) config('memberships.expiring_soon_days', 7)) + 1)->utc();
        $statistics = ['total' => (int) $profileCounts->total, 'active' => (clone $active)->count(), 'expiring' => (clone $base)->whereHas('currentMembership', fn ($q) => $q->where('status', 'active')->where('ends_at', '<=', $cutoff))->count(), 'inactive' => (int) $profileCounts->inactive];
        if (! empty($filters['plan_id'])) {
            MembershipPlan::findOrFail($filters['plan_id']);
        }
        $members = Member::with(['homeBranch:id,name,is_active', 'currentMembership', 'nextMembership', 'latestMembership']);
        MemberSummaryFilter::apply($members, ! empty($filters['plan_id']) ? (int) $filters['plan_id'] : null, $filters['membership_state'] ?? null, $timezone);
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $members->where(fn ($query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("mobile LIKE ? ESCAPE '!'", [$pattern]));
        }
        if ($status !== 'all') {
            $members->where('is_active', $status === 'active');
        }

        return Inertia::render('dashboard/members/Index', [
            'members' => $members->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 25)->withQueryString()->through(function ($member) use ($timezone, $canManage) {
                $summary = $member->currentMembership ?? $member->nextMembership ?? $member->latestMembership;

                return [
                    ...$member->only(['id', 'name', 'mobile', 'email', 'is_active']),
                    'branch' => $member->homeBranch?->name,
                    'membershipStatus' => ($member->currentMembership ?? $member->nextMembership ?? $member->latestMembership)?->displayStatus(null, $timezone) ?? 'No membership',
                    'canEdit' => (bool) $member->homeBranch?->is_active && $canManage,
                    'planName' => $summary?->plan_name,
                    'renewalDue' => $summary && $summary->status->value !== 'cancelled' ? $summary->ends_at->setTimezone($timezone)->toDateString() : null,
                ];
            }),
            'filters' => ['search' => $search, 'status' => $status, 'plan_id' => $filters['plan_id'] ?? null, 'membership_state' => $filters['membership_state'] ?? '', 'per_page' => (int) ($filters['per_page'] ?? 25)],
            'statistics' => $statistics,
            'planOptions' => MembershipPlan::orderBy('name')->get(['id', 'name'])->map(fn ($plan) => $plan->only(['id', 'name'])),
            'canCreate' => $canManage && Branch::where('is_active', true)->exists(),
            'paymentSelection' => $paymentSelection,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Member::class);

        return $this->form();
    }

    public function edit(Member $member): Response
    {
        Gate::authorize('update', $member);

        return $this->form($member);
    }

    public function show(Member $member): Response
    {
        Gate::authorize('update', $member);

        return app(MemberSubscriptionController::class)->index($member);
    }

    private function form(?Member $member = null, bool $readOnly = false): Response
    {
        $member?->load(['currentMembership', 'nextMembership', 'latestMembership']);
        $timezone = app(CurrentGym::class)->get()->timezone;

        return Inertia::render('dashboard/members/Edit', [
            ...PricingCatalog::data(),
            'member' => $member ? [...$member->only(['id', 'name', 'mobile', 'email', 'home_branch_id']), 'hasConsent' => $member->privacy_consented_at !== null,
                'membershipStatus' => ($member->currentMembership ?? $member->nextMembership ?? $member->latestMembership)?->displayStatus(null, $timezone) ?? 'No membership'] : null,
            'readOnly' => $readOnly,
            'plans' => $member ? [] : MembershipPlan::where('is_active', true)->orderBy('name')->orderBy('id')->get(['id', 'name', 'price_centavos', 'duration_amount', 'duration_unit'])->map(fn ($plan) => $plan->only(['id', 'name', 'price_centavos', 'duration_amount', 'duration_unit'])),
            'today' => now()->setTimezone($timezone)->toDateString(),
            'branches' => Branch::where(fn ($query) => $query->where('is_active', true)->when($member, fn ($query) => $query->orWhere('id', $member->home_branch_id)))->orderByDesc('is_main')->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn ($branch) => $branch->only(['id', 'name'])),
            'defaultBranchId' => app(CurrentGym::class)->branchId(),
            'canSave' => Branch::where('is_active', true)->when($member?->home_branch_id ?? app(CurrentGym::class)->viewBranchId(), fn ($query, $id) => $query->whereKey($id))->exists(),
        ]);
    }

    public function store(SaveMemberRequest $request, SaveMember $action): RedirectResponse
    {
        $action->handle($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return to_route('members.index');
    }

    public function update(SaveMemberRequest $request, Member $member, SaveMember $action): RedirectResponse
    {
        $action->handle($request->validated(), $member);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return to_route('members.index');
    }

    public function activation(MemberActivationRequest $request, Member $member, SaveMember $action): RedirectResponse
    {
        $active = $request->boolean('is_active');
        $action->activation($member, $active);
        Inertia::flash('toast', ['type' => 'success', 'message' => $active ? __('Member activated.') : __('Member deactivated.')]);

        return back();
    }
}
