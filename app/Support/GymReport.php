<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GymReport
{
    public function dateRange(array $data): array
    {
        $today = CarbonImmutable::now(app(CurrentGym::class)->get()->timezone);
        $month = match ($data['period'] ?? 'custom') {
            'this-month' => $today->startOfMonth(),
            'last-month' => $today->startOfMonth()->subMonth(),
            'month' => CarbonImmutable::createFromFormat('!Y-m', $data['month'], $today->timezone),
            default => null,
        };

        return match ($data['period'] ?? 'custom') {
            'this-year' => ['from' => $today->startOfYear()->toDateString(), 'through' => $today->toDateString()],
            'this-month' => ['from' => $month->toDateString(), 'through' => $today->toDateString()],
            'last-month', 'month' => ['from' => $month->toDateString(), 'through' => $month->endOfMonth()->toDateString()],
            default => ['from' => $data['from'] ?? $today->startOfMonth()->toDateString(), 'through' => $data['through'] ?? $today->toDateString()],
        };
    }

    public function filters(array $data): array
    {
        $c = app(CurrentGym::class);
        if (! empty($data['branch_id'])) {
            Branch::findOrFail($data['branch_id']);
        }

        return [...$this->dateRange($data), 'branch_id' => $c->viewBranchId() ?? (! empty($data['branch_id']) ? (int) $data['branch_id'] : null)];
    }

    public function build(array $filters): array
    {
        $timezone = app(CurrentGym::class)->get()->timezone;
        $start = CarbonImmutable::parse($filters['from'], $timezone)->startOfDay();
        $end = CarbonImmutable::parse($filters['through'], $timezone)->startOfDay()->addDay();
        $minutes = (int) ($start->offset / 60);
        $branch = $filters['branch_id'];
        $day = fn ($column) => DB::getDriverName() === 'sqlite' ? "date({$column}, '{$minutes} minutes')" : "DATE(DATE_ADD({$column}, INTERVAL {$minutes} MINUTE))";
        $attendance = AttendanceRecord::where('checked_in_at', '>=', $start->utc())->where('checked_in_at', '<', $end->utc())->when($branch, fn ($q) => $q->where('branch_id', $branch))->selectRaw($day('checked_in_at').' AS day, COUNT(*) AS total, COUNT(DISTINCT member_id) AS unique_members')->groupBy('day')->get()->keyBy('day');
        $revenue = Payment::where('paid_at', '>=', $start->utc())->where('paid_at', '<', $end->utc())->where('status', 'recorded')->when($branch, fn ($q) => $q->where('branch_id', $branch))->selectRaw($day('paid_at').' AS day, SUM(amount_centavos) AS total')->groupBy('day')->get()->keyBy('day');
        $renewals = MemberSubscription::where('created_at', '>=', $start->utc())->where('created_at', '<', $end->utc())->where('operation', 'renew')->where('status', '!=', 'cancelled')->when($branch, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('home_branch_id', $branch)))->selectRaw($day('member_subscriptions.created_at').' AS day, COUNT(*) AS total')->groupBy('day')->get()->keyBy('day');
        $rows = [];
        for ($at = $start; $at->lessThan($end); $at = $at->addDay()) {
            $date = $at->toDateString();
            $rows[] = ['date' => $date, 'checkIns' => (int) ($attendance[$date]->total ?? 0), 'uniqueMembers' => (int) ($attendance[$date]->unique_members ?? 0), 'renewals' => (int) ($renewals[$date]->total ?? 0), 'revenueCentavos' => (int) ($revenue[$date]->total ?? 0)];
        }

        return ['days' => $rows, 'summary' => ['checkIns' => array_sum(array_column($rows, 'checkIns')), 'renewals' => array_sum(array_column($rows, 'renewals')), 'revenueCentavos' => array_sum(array_column($rows, 'revenueCentavos'))]];
    }
}
