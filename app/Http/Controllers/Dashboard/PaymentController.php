<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\RecordPayment;
use App\Actions\VoidPayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RecordPaymentRequest;
use App\Http\Requests\Dashboard\VoidPaymentRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberPurchase;
use App\Models\Payment;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class PaymentController extends Controller
{
    public function show(Payment $payment): Response
    {
        Gate::authorize('view', $payment);
        $gym = app(CurrentGym::class)->get();
        $payment->load(['member:id,name', 'subscription:id,plan_name,starts_at,ends_at', 'purchase:id,plan_name,starts_at,ends_at', 'branch:id,name', 'recorder:id,name', 'voider:id,name']);
        $offering = $payment->subscription ?? $payment->purchase;

        return Inertia::render('dashboard/payments/Record', [
            'record' => [
                'id' => $payment->id, 'gymName' => $gym->name, 'branchName' => $payment->branch?->name,
                'memberName' => $payment->member?->name, 'planName' => $offering?->plan_name,
                'startsOn' => $offering?->starts_at->setTimezone($gym->timezone)->toDateString(),
                'validThrough' => $offering?->ends_at?->subSecond()->setTimezone($gym->timezone)->toDateString(),
                'createdAt' => $payment->created_at->setTimezone(app(CurrentGym::class)->get()->timezone)->format('M j, Y g:i A'), 'createdBy' => $payment->recorder?->name ?? __('Not recorded'), 'voidedBy' => $payment->voider?->name, 'amountCentavos' => $payment->amount_centavos, 'discountCentavos' => $payment->discount_centavos, 'discountPercent' => $payment->discount_basis_points / 100, 'discountScope' => $payment->discount_scope, 'discountBaseCentavos' => $payment->discount_base_centavos, 'method' => $payment->method->value, 'reference' => $payment->reference,
                'status' => $payment->status->value, 'paidAt' => $payment->paid_at->setTimezone($gym->timezone)->format('M j, Y g:i A'),
                'voidReason' => $payment->void_reason, 'voidedAt' => $payment->voided_at?->setTimezone($gym->timezone)->format('M j, Y g:i A'),
            ],
        ]);
    }

    public function index(Member $member): Response
    {
        return Inertia::render('dashboard/payments/History', $this->historyData($member));
    }

    public function historyData(Member $member, string $pageName = 'page'): array
    {
        Gate::authorize('update', $member);
        $timezone = app(CurrentGym::class)->get()->timezone;

        return [
            'member' => $member->only(['id', 'name']),
            'payments' => Payment::where('member_id', $member->id)->with(['branch:id,name,is_active', 'recorder:id,name', 'voider:id,name', 'subscription:id,plan_name', 'purchase:id,plan_name'])->orderByDesc('paid_at')->orderByDesc('id')->paginate(25, ['*'], $pageName)->withQueryString()->through(fn ($payment) => [
                'id' => $payment->id, 'createdAt' => $payment->created_at->setTimezone(app(CurrentGym::class)->get()->timezone)->format('M j, Y g:i A'), 'createdBy' => $payment->recorder?->name ?? __('Not recorded'), 'voidedBy' => $payment->voider?->name, 'amountCentavos' => $payment->amount_centavos, 'discountCentavos' => $payment->discount_centavos, 'discountPercent' => $payment->discount_basis_points / 100, 'discountScope' => $payment->discount_scope, 'discountBaseCentavos' => $payment->discount_base_centavos, 'method' => $payment->method->value,
                'reference' => $payment->reference, 'status' => $payment->status->value,
                'paidAt' => $payment->paid_at->setTimezone($timezone)->format('M j, Y g:i A'),
                'planName' => $payment->subscription?->plan_name ?? $payment->purchase?->plan_name ?? 'Offering payment',
                'branchName' => $payment->branch?->name,
                'voidReason' => $payment->void_reason,
                'voidedAt' => $payment->voided_at?->setTimezone($timezone)->format('M j, Y g:i A'),
                // The member policy and recorded-branch query scope already authorize this list.
                'canVoid' => $payment->status === PaymentStatus::Recorded && (bool) $payment->branch?->is_active,
            ]),
        ];
    }

    public function void(VoidPaymentRequest $request, Payment $payment, VoidPayment $action): RedirectResponse
    {
        $action->handle($payment, $request->validated('void_reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment voided. No refund was sent.')]);

        return back();
    }

    public function create(Member $member): Response
    {
        return Inertia::render('dashboard/payments/Create', $this->formData($member));
    }

    public function formData(Member $member, string $pageName = 'page'): array
    {
        Gate::authorize('update', $member);
        $gym = app(CurrentGym::class)->get();
        $paid = Payment::withoutGlobalScope('branch')->selectRaw('member_subscription_id, SUM(amount_centavos + discount_centavos) AS total_paid')
            ->where('gym_id', $gym->id)->where('member_id', $member->id)->where('status', 'recorded')->groupBy('member_subscription_id');
        $purchasePaid = Payment::withoutGlobalScope('branch')->where('member_id', $member->id)->where('status', 'recorded')->whereNotNull('member_purchase_id')->selectRaw('member_purchase_id, SUM(amount_centavos + discount_centavos) AS total_paid')->groupBy('member_purchase_id');
        $writableBranches = Branch::where('is_active', true)->pluck('id')->all();
        $purchases = MemberPurchase::where('member_id', $member->id)->leftJoinSub($purchasePaid, 'paid', fn ($join) => $join->on('member_purchases.id', '=', 'paid.member_purchase_id'))->where('member_purchases.status', '!=', 'cancelled')->orderByDesc('member_purchases.id')->paginate(25, ['member_purchases.*', 'paid.total_paid'], $pageName.'_purchases')->withQueryString()->through(fn ($purchase) => [
            'id' => $purchase->id, 'planName' => $purchase->plan_name, 'kind' => $purchase->kind, 'startsOn' => $purchase->starts_at->setTimezone($gym->timezone)->toDateString(), 'duration' => $purchase->kind === 'package' ? $purchase->sessions.' sessions' : 'Membership benefit', 'balanceCentavos' => max(0, $purchase->price_centavos - (int) $purchase->total_paid), 'canRecord' => in_array($purchase->branch_id, $writableBranches),
        ]);

        return [
            'member' => $member->only(['id', 'name']),
            'canRecord' => in_array($member->home_branch_id, $writableBranches) || $purchases->getCollection()->contains(fn ($p) => $p['canRecord']),
            'purchases' => $purchases,
            'subscriptions' => $member->subscriptions()->leftJoinSub($paid, 'paid', fn ($join) => $join->on('member_subscriptions.id', '=', 'paid.member_subscription_id'))
                ->where('member_subscriptions.status', '!=', 'cancelled')->orderByDesc('starts_at')->orderByDesc('member_subscriptions.id')
                ->paginate(25, ['member_subscriptions.*', 'paid.total_paid'], $pageName)->withQueryString()->through(fn ($subscription) => [
                    'id' => $subscription->id, 'planName' => $subscription->plan_name, 'duration' => $subscription->duration_amount.' '.$subscription->duration_unit, 'canRecord' => in_array($member->home_branch_id, $writableBranches),
                    'startsOn' => $subscription->starts_at->setTimezone($gym->timezone)->toDateString(),
                    'balanceCentavos' => max(0, $subscription->price_centavos - (int) $subscription->total_paid),
                ]),
        ];
    }

    public function store(RecordPaymentRequest $request, Member $member, RecordPayment $action): RedirectResponse
    {
        $action->handle($member, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offline payment recorded.')]);
        if ($request->boolean('return_to_purchases') && Feature::active('members')) {
            return to_route('purchases.index', $member);
        }

        if ($request->boolean('return_to_profile') && Feature::active('members')) {
            return to_route('members.show', ['member' => $member, 'tab' => 'payment']);
        }

        return to_route('payments.create', $member);
    }
}
