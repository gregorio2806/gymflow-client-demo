<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\SaveMembershipPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\MembershipPlanActivationRequest;
use App\Http\Requests\Dashboard\SaveMembershipPlanRequest;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\PricingDiscount;
use App\Support\Audit;
use App\Support\PaymentDiscount;
use App\Support\PricingCatalog;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MembershipPlanController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MembershipPlan::class);
        $filters = $request->validate(['status' => ['nullable', Rule::in(['all', 'active', 'inactive'])], 'tab' => ['nullable', Rule::in(['overview', 'subscription', 'benefit', 'discounts', 'package'])], 'search' => ['nullable', 'string', 'max:100']]);
        $status = $filters['status'] ?? 'all';
        $tab = ($filters['tab'] ?? 'subscription') === 'overview' ? 'subscription' : ($filters['tab'] ?? 'subscription');
        $search = trim($filters['search'] ?? '');

        return Inertia::render('dashboard/membership-plans/Index', [
            'plans' => MembershipPlan::withoutGlobalScope('access-products')->when(in_array($tab, ['subscription', 'benefit', 'package']), fn ($q) => $q->where('kind', $tab))->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%'))->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))->orderBy('kind')->orderBy('name')->orderBy('id')->paginate(25)->withQueryString()->through(fn ($plan) => $plan->only(['id', 'name', 'kind', 'branch_ids', 'configuration', 'duration_amount', 'duration_unit', 'price_centavos', 'inclusions', 'is_active'])),
            'status' => $status,
            'tab' => $tab,
            'search' => $search,
            'discountRules' => PricingDiscount::orderBy('id')->get()->map(fn ($d) => $d->only(['id', 'name', 'category', 'rule_type', 'unit', 'value', 'requires_verification', 'is_active', 'product_kinds', 'plan_ids', 'effective_from', 'effective_through'])),
            'discountOfferings' => MembershipPlan::withoutGlobalScope('access-products')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind'])->map(fn ($p) => $p->only(['id', 'name', 'kind'])),
            'pricingSettings' => PricingCatalog::settings(app(CurrentGym::class)->get()),
            'canManage' => Gate::allows('create', MembershipPlan::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', MembershipPlan::class);

        $kind = $request->validate(['kind' => ['nullable', Rule::in(['subscription', 'benefit', 'package'])]])['kind'] ?? 'subscription';

        return Inertia::render('dashboard/membership-plans/Edit', ['plan' => null, 'kind' => $kind, 'readOnly' => false, 'canManage' => true, 'history' => null, ...PricingCatalog::data(), 'coaches' => $this->coaches()]);
    }

    public function edit(MembershipPlan $membershipPlan): Response
    {
        Gate::authorize('update', $membershipPlan);

        return $this->detail($membershipPlan, false);
    }

    public function show(MembershipPlan $membershipPlan): Response
    {
        Gate::authorize('viewAny', MembershipPlan::class);

        return $this->detail($membershipPlan, true);
    }

    private function detail(MembershipPlan $membershipPlan, bool $readOnly): Response
    {
        $membershipPlan->load(['creationAudit.user:id,name', 'updateAudit.user:id,name']);
        $history = AuditLog::where('subject_type', $membershipPlan->getMorphClass())->where('subject_id', $membershipPlan->id)->whereIn('action', ['membership_plan.created', 'membership_plan.updated', 'membership_plan.activated', 'membership_plan.archived'])->with('user:id,name')->latest('id')->paginate(25)->withQueryString()->through(function (AuditLog $log) {
            $before = $log->changes['before'] ?? [];
            $after = $log->changes['after'] ?? [];
            $details = [];
            if ($log->action === 'membership_plan.updated') {
                foreach (['name' => 'Name', 'price_centavos' => 'Price', 'duration_amount' => 'Duration', 'duration_unit' => 'Duration unit', 'inclusions' => 'Description', 'branch_ids' => 'Eligible branches', 'configuration' => 'Offering settings'] as $field => $label) {
                    if (($before[$field] ?? null) === ($after[$field] ?? null)) {
                        continue;
                    }
                    if (in_array($field, ['branch_ids', 'configuration'])) {
                        $details[] = $label.' updated';
                    } else {
                        $format = fn ($value) => $field === 'price_centavos' && $value !== null ? '₱'.number_format($value / 100, 2) : ($value === null || $value === '' ? __('Not set') : (string) $value);
                        $details[] = $label.': '.$format($before[$field] ?? null).' → '.$format($after[$field] ?? null);
                    }
                }
            }

            return ['id' => $log->id, 'action' => match ($log->action) {
                'membership_plan.created' => 'Created', 'membership_plan.updated' => 'Updated', 'membership_plan.activated' => 'Activated', 'membership_plan.archived' => 'Archived',
            }, 'at' => $log->created_at->setTimezone(app(CurrentGym::class)->get()->timezone)->format('F j, Y g:i A'), 'by' => $log->user?->name ?? __('Not recorded'), 'details' => $details];
        });

        return Inertia::render('dashboard/membership-plans/Edit', ['plan' => [...$membershipPlan->only(['id', 'name', 'kind', 'branch_ids', 'configuration', 'duration_amount', 'duration_unit', 'inclusions']), ...$this->metadata($membershipPlan), 'price_php' => intdiv($membershipPlan->price_centavos, 100).'.'.str_pad((string) ($membershipPlan->price_centavos % 100), 2, '0', STR_PAD_LEFT)], 'kind' => $membershipPlan->kind, 'readOnly' => $readOnly, 'canManage' => Gate::allows('update', $membershipPlan), 'history' => $history, ...PricingCatalog::data(), 'coaches' => $this->coaches()]);
    }

    private function coaches(): array
    {
        return app(CurrentGym::class)->get()->users()->wherePivot('role', 'coach')->wherePivot('is_active', true)->get(['users.id', 'users.name'])->map(fn ($u) => $u->only(['id', 'name']))->all();
    }

    public function settings(Request $request): RedirectResponse
    {
        Gate::authorize('create', MembershipPlan::class);
        $data = $request->validate(['require_purchase' => ['required', 'boolean'], 'packages_require_access' => ['required', 'boolean'], 'promotion_stacking' => ['required', Rule::in(['none', 'additive'])], 'tax_profile' => ['required', Rule::in(['unconfirmed', 'non-vat', 'vat-inclusive'])]]);
        DB::transaction(function () use ($data) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $before = PricingCatalog::settings($gym);
            $gym->forceFill(['pricing_settings' => $data])->save();
            Audit::log('pricing.settings_updated', $gym, ['before' => $before, 'after' => $data]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pricing policy saved.')]);

        return back();
    }

    public function discount(Request $request, ?PricingDiscount $pricingDiscount = null): RedirectResponse
    {
        Gate::authorize('create', MembershipPlan::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'category' => ['required', Rule::in(['regular', 'student', 'pwd', 'senior', 'others']), Rule::unique('pricing_discounts')->where('gym_id', app(CurrentGym::class)->id())->ignore($pricingDiscount?->id)], 'unit' => ['required', Rule::in(['percentage', 'peso'])], 'input' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'], 'requires_verification' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'], 'product_kinds' => ['required', 'array', 'min:1', 'max:3'], 'product_kinds.*' => [Rule::in(['subscription', 'benefit', 'package'])], 'plan_ids' => ['nullable', 'array', 'max:100'], 'plan_ids.*' => ['integer', 'distinct', Rule::exists('membership_plans', 'id')->where('gym_id', app(CurrentGym::class)->id())], 'effective_from' => ['nullable', 'date_format:Y-m-d'], 'effective_through' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from']]);
        DB::transaction(function () use ($data, $pricingDiscount) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            if ($gym->pricing_settings === null) {
                $gym->forceFill(['pricing_settings' => PricingCatalog::settings($gym)])->save();
            }
            $row = $pricingDiscount ? PricingDiscount::lockForUpdate()->findOrFail($pricingDiscount->id) : new PricingDiscount;
            $statutory = in_array($data['category'], ['pwd', 'senior']);
            $before = $row->only(['name', 'category', 'rule_type', 'unit', 'value', 'is_active', 'requires_verification', 'product_kinds', 'plan_ids', 'effective_from', 'effective_through']);
            $row->forceFill(Arr::except($data, ['input']));
            $row->rule_type = $statutory ? 'statutory' : 'promotional';
            $row->value = $statutory ? 2000 : ($data['category'] === 'regular' ? 0 : ($data['unit'] === 'percentage' ? PaymentDiscount::percentage($data['input']) : PaymentDiscount::pesos($data['input'])));
            if ($data['category'] === 'regular') {
                $row->requires_verification = false;
            }
            if ($statutory) {
                $row->forceFill(['unit' => 'percentage', 'requires_verification' => true, 'product_kinds' => ['subscription', 'package']]);
            }
            $row->save();
            Audit::log($pricingDiscount ? 'pricing.discount_updated' : 'pricing.discount_created', $row, ['before' => $before, 'after' => $row->only(['name', 'category', 'rule_type', 'unit', 'value', 'is_active', 'requires_verification', 'product_kinds', 'plan_ids', 'effective_from', 'effective_through'])]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Discount saved.')]);

        return back();
    }

    private function metadata(MembershipPlan $plan): array
    {
        $timezone = app(CurrentGym::class)->get()->timezone;

        return [
            'createdAt' => $plan->created_at->setTimezone($timezone)->format('M j, Y g:i A'),
            'createdBy' => $plan->creationAudit?->user?->name ?? __('Not recorded'),
            'updatedAt' => $plan->updateAudit?->created_at->setTimezone($timezone)->format('M j, Y g:i A'),
            'updatedBy' => $plan->updateAudit?->user?->name,
        ];
    }

    public function store(SaveMembershipPlanRequest $request, SaveMembershipPlan $action): RedirectResponse
    {
        $plan = $action->handle($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership plan added.')]);

        return to_route('membership-plans.index', ['tab' => $plan->kind]);
    }

    public function update(SaveMembershipPlanRequest $request, MembershipPlan $membershipPlan, SaveMembershipPlan $action): RedirectResponse
    {
        $action->handle($request->validated(), $membershipPlan);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership plan updated.')]);

        return to_route('membership-plans.edit', $membershipPlan);
    }

    public function activation(MembershipPlanActivationRequest $request, MembershipPlan $membershipPlan, SaveMembershipPlan $action): RedirectResponse
    {
        $active = $request->boolean('is_active');
        $action->availability($membershipPlan, $active);
        Inertia::flash('toast', ['type' => 'success', 'message' => $active ? __('Membership plan activated.') : __('Membership plan archived.')]);

        return back();
    }
}
