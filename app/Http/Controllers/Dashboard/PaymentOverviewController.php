<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PaymentOverviewRequest;
use App\Models\Branch;
use App\Models\MemberPurchase;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentOverviewController extends Controller
{
    public function __invoke(PaymentOverviewRequest $request): Response
    {
        $gym = app(CurrentGym::class)->get();
        $filters = $request->validated();
        $view = $filters['view'] ?? 'day';
        $date = $filters['date'] ?? CarbonImmutable::now($gym->timezone)->toDateString();
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, $gym->timezone);
        if ($view === 'month') {
            $start = $start->startOfMonth();
        }
        $end = $view === 'month' ? $start->addMonth() : $start->addDay();
        $branchId = app(CurrentGym::class)->viewBranchId() ?? ($filters['branch_id'] ?? null);
        if (! empty($filters['branch_id'])) {
            Branch::findOrFail($filters['branch_id']);
        }
        $payments = Payment::where('paid_at', '>=', $start->utc())->where('paid_at', '<', $end->utc())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $revenue = (clone $payments)->where('status', 'recorded')->sum('amount_centavos');
        $paid = Payment::withoutGlobalScope('branch')->selectRaw('member_subscription_id, SUM(amount_centavos + discount_centavos) AS paid_centavos')
            ->where('gym_id', $gym->id)->where('status', 'recorded')->groupBy('member_subscription_id');
        $balances = MemberSubscription::leftJoinSub($paid, 'paid', fn ($join) => $join->on('member_subscriptions.id', '=', 'paid.member_subscription_id'))
            ->where('member_subscriptions.status', '!=', 'cancelled')
            ->whereRaw('member_subscriptions.price_centavos > COALESCE(paid.paid_centavos, 0)')
            ->when($branchId, fn ($query) => $query->whereHas('member', fn ($member) => $member->where('home_branch_id', $branchId)))
            ->when(($filters['balance'] ?? 'unpaid') === 'overdue', fn ($query) => $query->where('starts_at', '<=', now()->utc()));
        $outstanding = (clone $balances)->selectRaw('COALESCE(SUM(member_subscriptions.price_centavos - COALESCE(paid.paid_centavos, 0)), 0) AS outstanding')->first();
        $purchasePaid = Payment::withoutGlobalScope('branch')->where('gym_id', $gym->id)->where('status', 'recorded')->whereNotNull('member_purchase_id')->selectRaw('member_purchase_id, SUM(amount_centavos + discount_centavos) as settled')->groupBy('member_purchase_id');
        $purchaseBalances = MemberPurchase::leftJoinSub($purchasePaid, 'settled', fn ($join) => $join->on('member_purchases.id', '=', 'settled.member_purchase_id'))->where('member_purchases.status', '!=', 'cancelled')->whereRaw('member_purchases.price_centavos > COALESCE(settled.settled, 0)')->when($branchId, fn ($q) => $q->where('member_purchases.branch_id', $branchId))->when(($filters['balance'] ?? 'unpaid') === 'overdue', fn ($q) => $q->where('starts_at', '<=', now()->utc()));
        $packageOutstanding = (clone $purchaseBalances)->selectRaw('COALESCE(SUM(member_purchases.price_centavos - COALESCE(settled.settled, 0)), 0) as outstanding')->first()->outstanding;
        $rows = (clone $balances)->selectRaw("member_subscriptions.id, member_subscriptions.member_id, member_subscriptions.plan_name, member_subscriptions.starts_at, member_subscriptions.price_centavos - COALESCE(paid.paid_centavos, 0) as balance, 'subscription' as kind")->toBase()->unionAll((clone $purchaseBalances)->selectRaw('member_purchases.id, member_purchases.member_id, member_purchases.plan_name, member_purchases.starts_at, member_purchases.price_centavos - COALESCE(settled.settled, 0) as balance, member_purchases.kind')->toBase());
        $allBalances = DB::query()->fromSub($rows, 'balances')->join('members', 'members.id', '=', 'balances.member_id')->where('members.gym_id', $gym->id)->select('balances.*', 'members.name as member_name');

        return Inertia::render('dashboard/payments/Overview', [
            'filters' => ['view' => $view, 'date' => $date, 'branch_id' => $branchId ? (int) $branchId : null, 'balance' => $filters['balance'] ?? 'unpaid'],
            'summary' => ['revenueCentavos' => (int) $revenue, 'outstandingCentavos' => (int) $outstanding->outstanding + (int) $packageOutstanding],
            'branches' => Branch::orderByDesc('is_main')->orderBy('name')->get(['id', 'name'])->map(fn ($branch) => $branch->only(['id', 'name'])),
            'payments' => $payments->with(['member:id,name', 'branch:id,name', 'recorder:id,name', 'voider:id,name'])->orderByDesc('paid_at')->orderByDesc('id')->paginate(25, ['*'], 'payment_page')->withQueryString()->through(fn ($payment) => [
                'createdAt' => $payment->created_at->setTimezone($gym->timezone)->format('M j, Y g:i A'), 'createdBy' => $payment->recorder?->name ?? __('Not recorded'), 'id' => $payment->id, 'memberId' => $payment->member_id, 'memberName' => $payment->member?->name,
                'branchName' => $payment->branch?->name, 'amountCentavos' => $payment->amount_centavos,
                'status' => $payment->status->value, 'method' => $payment->method->value,
                'paidAt' => $payment->paid_at->setTimezone($gym->timezone)->format('M j, Y g:i A'),
                'voidedAt' => $payment->voided_at?->setTimezone($gym->timezone)->format('M j, Y g:i A'),
                'voidedBy' => $payment->voider?->name,
            ]),
            'balances' => $allBalances->orderBy('starts_at')->orderBy('kind')->orderBy('id')->paginate(25, ['*'], 'balance_page')->withQueryString()->through(fn ($subscription) => [
                'id' => $subscription->kind === 'subscription' ? (int) $subscription->id : $subscription->kind.'-'.$subscription->id, 'kind' => $subscription->kind, 'memberId' => $subscription->member_id, 'memberName' => $subscription->member_name,
                'planName' => $subscription->plan_name, 'startsOn' => CarbonImmutable::parse($subscription->starts_at, 'UTC')->setTimezone($gym->timezone)->toDateString(),
                'balanceCentavos' => (int) $subscription->balance,
                'overdue' => CarbonImmutable::parse($subscription->starts_at, 'UTC')->lessThanOrEqualTo(now()),
            ]),
        ]);
    }
}
