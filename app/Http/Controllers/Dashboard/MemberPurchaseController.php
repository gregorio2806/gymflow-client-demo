<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\PurchaseMemberOfferings;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\SessionRedemption;
use App\Support\Audit;
use App\Support\PaymentDiscount;
use App\Support\PlanLimits;
use App\Support\PricingCatalog;
use App\Support\PricingCheckout;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MemberPurchaseController extends Controller
{
    public function quote(Request $request)
    {
        $data = $request->validate([...PricingCheckout::rules(), 'member_id' => ['nullable', 'integer']]);
        $member = ! empty($data['member_id']) ? Member::findOrFail($data['member_id']) : null;
        Gate::authorize($member ? 'update' : 'create', $member ?? Member::class);

        return response()->json(app(PricingCheckout::class)->quote($data, $member))->header('Cache-Control', 'private, no-store');
    }

    public function index(Member $member)
    {
        Gate::authorize('update', $member);
        $timezone = app(CurrentGym::class)->get()->timezone;
        $settled = Payment::withoutGlobalScope('branch')->where('member_id', $member->id)->where('status', 'recorded')->whereNotNull('member_purchase_id')->selectRaw('member_purchase_id, SUM(amount_centavos + discount_centavos) AS settled')->groupBy('member_purchase_id')->pluck('settled', 'member_purchase_id');
        $records = MemberPurchase::with(['creator:id,name', 'updateAudit.user:id,name'])->where('member_id', $member->id)->latest('id')->paginate(25);
        $reservedPeriods = [
            ...MemberSubscription::withoutGlobalScope('branch')->where('member_id', $member->id)->where('status', '!=', 'cancelled')->where('ends_at', '>', now())->get(['plan_name', 'starts_at', 'ends_at'])->map(fn ($period) => ['kind' => 'subscription', 'planName' => $period->plan_name, 'startsOn' => $period->starts_at->setTimezone($timezone)->toDateString(), 'endsOn' => $period->ends_at->setTimezone($timezone)->toDateString()])->all(),
            ...MemberPurchase::withoutGlobalScope('branch')->where('member_id', $member->id)->where('kind', 'benefit')->where('status', 'active')->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->get(['plan_name', 'starts_at', 'ends_at'])->map(fn ($purchase) => ['kind' => 'benefit', 'planName' => $purchase->plan_name, 'startsOn' => $purchase->starts_at->setTimezone($timezone)->toDateString(), 'endsOn' => $purchase->ends_at?->setTimezone($timezone)->toDateString()])->all(),
        ];

        return Inertia::render('dashboard/members/Purchases', [
            'member' => [...$member->only(['id', 'name', 'pricing_discount_id', 'eligibility_from', 'eligibility_through']), 'reservedPeriods' => $reservedPeriods],
            'canSave' => Branch::whereKey($member->home_branch_id)->where('is_active', true)->exists(),
            'renewalAvailable' => MemberSubscription::where('member_id', $member->id)->where('status', '!=', 'cancelled')->exists(),
            'eligibilityVerified' => $member->eligibility_verified_at !== null,
            ...PricingCatalog::data(),
            'redemptions' => SessionRedemption::whereIn('member_purchase_id', $records->getCollection()->pluck('id'))->with(['recorder:id,name', 'schedule:id,starts_at'])->latest('id')->limit(25)->get()->map(fn ($r) => ['id' => $r->id, 'purchaseId' => $r->member_purchase_id, 'appointmentId' => $r->coach_schedule_id, 'date' => $r->schedule?->starts_at->setTimezone($timezone)->format('F j, Y g:i A'), 'createdAt' => $r->created_at->setTimezone($timezone)->format('F j, Y g:i A'), 'createdBy' => $r->recorder?->name ?? 'Not recorded']),
            'purchases' => $records->through(fn ($p) => [
                ...$p->only(['id', 'kind', 'plan_name', 'price_centavos', 'original_price_centavos', 'sessions', 'used_sessions', 'session_adjustment', 'snapshot']),
                'remaining' => $p->remaining(), 'balance' => max(0, $p->price_centavos - ($settled[$p->id] ?? 0)),
                'status' => $p->status === 'cancelled' ? 'Cancelled' : ($p->ends_at && $p->ends_at <= now() ? 'Expired' : ($p->starts_at > now() ? 'Scheduled' : 'Active')),
                'startsOn' => $p->starts_at->setTimezone($timezone)->format('F j, Y'), 'validThrough' => $p->ends_at?->subDay()->setTimezone($timezone)->format('F j, Y') ?? 'No expiry',
                'createdAt' => $p->created_at->setTimezone($timezone)->format('F j, Y g:i A'), 'createdBy' => $p->creator?->name ?? 'Not recorded',
                'updatedAt' => $p->updateAudit?->created_at->setTimezone($timezone)->format('F j, Y g:i A'), 'updatedBy' => $p->updateAudit?->user?->name,
            ]),
            'canManage' => Gate::allows('create', MembershipPlan::class),
        ]);
    }

    public function store(Request $request, Member $member, PurchaseMemberOfferings $action)
    {
        Gate::authorize('update', $member);
        $data = $request->validate(PricingCheckout::rules());
        $action->handle($member, $data);
        $paid = ! empty($data['paid_php']) && PaymentDiscount::pesos($data['paid_php']) > 0;
        Inertia::flash('toast', ['type' => 'success', 'message' => __($paid ? 'Purchase and payment saved.' : 'Purchase saved as unpaid.')]);

        return back();
    }

    public function change(Request $request, Member $member, MemberPurchase $purchase)
    {
        Gate::authorize('update', $member);
        Gate::authorize('create', MembershipPlan::class);
        $data = $request->validate(['action' => ['required', Rule::in(['cancel', 'adjust'])], 'adjustment' => ['required_if:action,adjust', 'integer', 'between:-10000,10000'], 'expected_adjustment' => ['required_if:action,adjust', 'integer'], 'expected_used' => ['required_if:action,adjust', 'integer'], 'reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($member, $purchase, $data) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $purchase = MemberPurchase::where('member_id', $member->id)->lockForUpdate()->findOrFail($purchase->id);
            app(PlanLimits::class)->ensureBranchWritable($gym, $purchase->branch_id);
            if ($purchase->status === 'cancelled') {
                throw ValidationException::withMessages(['action' => __('This purchase is already cancelled.')]);
            }
            $before = $purchase->only(['status', 'session_adjustment', 'used_sessions']);
            if ($data['action'] === 'cancel') {
                $purchase->status = 'cancelled';
            } else {
                if ($purchase->session_adjustment !== (int) $data['expected_adjustment'] || $purchase->used_sessions !== (int) $data['expected_used']) {
                    throw ValidationException::withMessages(['adjustment' => __('This balance changed. Refresh before adjusting sessions again.')]);
                }
                if ($purchase->kind !== 'package' || $purchase->sessions + $purchase->session_adjustment + $data['adjustment'] < $purchase->used_sessions) {
                    throw ValidationException::withMessages(['adjustment' => __('An adjustment must leave a nonnegative package balance.')]);
                }
                $purchase->session_adjustment += $data['adjustment'];
            }
            $purchase->save();
            Audit::log('purchase.'.$data['action'], $purchase, ['before' => $before, 'after' => $purchase->only(['status', 'session_adjustment', 'used_sessions']), 'reason' => $data['reason']]);
        });

        return back();
    }
}
