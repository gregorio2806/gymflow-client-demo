<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Member;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardAnalytics
{
    public function build(string $period, ?array $counts, bool $paymentsEnabled, bool $attendanceEnabled): array
    {
        $context = app(CurrentGym::class);
        $now = CarbonImmutable::now($context->get()->timezone);
        $today = $now->startOfDay();
        $start = match ($period) {
            'week' => $today->startOfWeek(),
            'year' => $today->startOfYear(),
            default => $today->startOfMonth(),
        };
        $end = $now->addSecond();
        $previousStart = $start->subSeconds((int) $start->diffInSeconds($end));
        $data = ['period' => ['value' => $period, 'from' => $start->toDateString(), 'through' => $today->toDateString()], 'revenue' => null, 'attendance' => null, 'membership' => null, 'memberStats' => null];
        $members = $context->filterView(Member::query(), 'home_branch_id');
        if ($counts !== null) {
            $segments = [
                ['key' => 'active', 'label' => 'Active', 'count' => max(0, $counts['active'] - $counts['expiringThisWeek'])],
                ['key' => 'expiring-soon', 'label' => 'Expiring (within 7 days)', 'count' => $counts['expiringThisWeek']],
                ['key' => 'expired', 'label' => 'Expired', 'count' => $counts['expired']],
            ];
            foreach (['suspended' => 'Suspended', 'scheduled' => 'Scheduled', 'cancelled' => 'Cancelled', 'none' => 'No membership'] as $state => $label) {
                $query = clone $members;
                MemberSummaryFilter::apply($query, null, $state, $context->get()->timezone);
                $segments[] = ['key' => $state, 'label' => $label, 'count' => $query->count()];
            }
            $total = (clone $members)->count();
            if ($total > array_sum(array_column($segments, 'count'))) {
                $segments[] = ['key' => 'other', 'label' => 'Other', 'count' => $total - array_sum(array_column($segments, 'count'))];
            }
            $data['membership'] = ['total' => $total, 'segments' => $segments];
            $data['memberStats'] = [
                'newMembers' => (clone $members)->where('created_at', '>=', $start->utc())->where('created_at', '<', $end->utc())->count(),
                'renewals' => MemberSubscription::where('operation', 'renew')->where('status', '!=', 'cancelled')->where('created_at', '>=', $start->utc())->where('created_at', '<', $end->utc())->when($context->viewBranchId(), fn ($q, $id) => $q->whereHas('member', fn ($q) => $q->where('home_branch_id', $id)))->count(),
            ];
        }
        if ($paymentsEnabled) {
            $payments = $context->filterView(Payment::where('status', 'recorded'));
            $current = (clone $payments)->where('paid_at', '>=', $start->utc())->where('paid_at', '<', $end->utc())->sum('amount_centavos');
            $previous = (clone $payments)->where('paid_at', '>=', $previousStart->utc())->where('paid_at', '<', $start->utc())->sum('amount_centavos');
            $firstMonth = $today->startOfMonth()->subMonths(11);
            $months = (clone $payments)->where('paid_at', '>=', $firstMonth->utc())->where('paid_at', '<', $end->utc())->selectRaw($this->bucket('paid_at', $now, true).' AS bucket, SUM(amount_centavos) AS total')->groupBy('bucket')->get()->keyBy('bucket');
            $series = [];
            for ($at = $firstMonth; $at->lessThanOrEqualTo($today); $at = $at->addMonth()) {
                $series[] = ['label' => $at->format('M Y'), 'value' => (int) ($months[$at->format('Y-m')]->total ?? 0)];
            }
            $data['revenue'] = ['totalCentavos' => (int) $current, 'changePercent' => $this->change((int) $current, (int) $previous), 'months' => $series,
                'methods' => (clone $payments)->where('paid_at', '>=', $start->utc())->where('paid_at', '<', $end->utc())->selectRaw('method, SUM(amount_centavos) AS total')->groupBy('method')->get()->map(fn ($row) => ['label' => match ($row->method->value) {
                    'cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya', default => 'Bank transfer'
                }, 'value' => (int) $row->total])->values()->all()];
        }
        if ($attendanceEnabled) {
            $attendance = $context->filterView(AttendanceRecord::query());
            $current = (clone $attendance)->where('checked_in_at', '>=', $today->utc())->where('checked_in_at', '<', $end->utc())->count();
            $previous = (clone $attendance)->where('checked_in_at', '>=', $today->subDay()->utc())->where('checked_in_at', '<', $today->utc())->count();
            $chartStart = $today->subDays(29);
            $allStart = $chartStart->lessThan($start) ? $chartStart : $start;
            $days = (clone $attendance)->where('checked_in_at', '>=', $allStart->utc())->where('checked_in_at', '<', $end->utc())->selectRaw($this->bucket('checked_in_at', $now).' AS bucket, COUNT(*) AS total')->groupBy('bucket')->get()->keyBy('bucket');
            $series = [];
            for ($at = $chartStart; $at->lessThanOrEqualTo($today); $at = $at->addDay()) {
                $series[] = ['label' => $at->format('M j'), 'value' => (int) ($days[$at->toDateString()]->total ?? 0)];
            }
            $periodDays = $days->filter(fn ($row, $date) => $date >= $start->toDateString());
            $total = (int) $periodDays->sum('total');
            $busiest = $total > 0 ? $periodDays->sortByDesc('total')->keys()->first() : null;
            $data['attendance'] = ['today' => $current, 'changePercent' => $this->change($current, $previous), 'days' => $series, 'periodTotal' => $total,
                'dailyAverage' => round($total / max(1, $start->diffInDays($today) + 1), 1), 'busiestDay' => $busiest];
        }

        return $data;
    }

    private function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    private function bucket(string $column, CarbonImmutable $now, bool $month = false): string
    {
        $minutes = (int) ($now->offset / 60);
        if (DB::getDriverName() === 'sqlite') {
            return $month ? "strftime('%Y-%m', {$column}, '{$minutes} minutes')" : "date({$column}, '{$minutes} minutes')";
        }

        return $month ? "DATE_FORMAT(DATE_ADD({$column}, INTERVAL {$minutes} MINUTE), '%Y-%m')" : "DATE(DATE_ADD({$column}, INTERVAL {$minutes} MINUTE))";
    }
}
