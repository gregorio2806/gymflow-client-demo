<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AttendanceHistoryRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(AttendanceHistoryRequest $request): Response|StreamedResponse
    {
        $timezone = app(CurrentGym::class)->get()->timezone;
        $filters = $request->validated();
        $view = $filters['view'] ?? 'day';
        $date = $filters['date'] ?? CarbonImmutable::now($timezone)->toDateString();
        $anchor = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $start = match ($view) {
            'week' => $anchor->startOfWeek(CarbonImmutable::MONDAY),
            'month' => $anchor->startOfMonth(),
            default => $anchor,
        };
        $end = match ($view) {
            'week' => $start->addWeek(),
            'month' => $start->addMonth(),
            default => $start->addDay(),
        };
        $branchId = app(CurrentGym::class)->viewBranchId() ?? ($filters['branch_id'] ?? null);
        if (! empty($filters['branch_id'])) {
            // Reject foreign or manager-inaccessible branches rather than silently changing filters.
            Branch::findOrFail($filters['branch_id']);
        }
        $query = AttendanceRecord::where('checked_in_at', '>=', $start->utc())->where('checked_in_at', '<', $end->utc());
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->whereHas('member', fn ($member) => $member->where(fn ($match) => $match
                ->where('name', 'like', '%'.$search.'%')->orWhere('mobile', 'like', '%'.$search.'%')->orWhere('id', $search)));
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        $canExport = Feature::for(app(CurrentGym::class)->get())->active('full-reports');
        if (($filters['export'] ?? null) === 'csv') {
            abort_unless($canExport, 404);

            return response()->streamDownload(function () use ($query, $timezone) {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['Member', 'Mobile', 'Check-in (Manila)', 'Branch', 'Source', 'Status', 'Created by'], ',', '"', '');
                $safe = fn ($value) => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : $value;
                (clone $query)->with(['member:id,name,mobile', 'branch:id,name', 'recorder:id,name'])->chunkById(500, function ($records) use ($stream, $timezone, $safe) {
                    foreach ($records as $record) {
                        fputcsv($stream, array_map($safe, [$record->member?->name ?? 'Removed member', $record->member?->mobile ?? '', $record->checked_in_at->setTimezone($timezone)->format('F j, Y g:i A'), $record->branch?->name ?? 'Removed branch', $record->source === 'qr' ? 'QR scan' : 'Manual', 'Present', $record->recorder?->name ?? 'Not recorded']), ',', '"', '');
                    }
                });
                fclose($stream);
            }, 'attendance-'.$start->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        $summary = (clone $query)->selectRaw('COUNT(*) as check_ins, COUNT(DISTINCT member_id) as unique_members')->first();
        $offset = $start->offset;
        $hour = $query->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', checked_in_at, '{$offset} seconds') AS INTEGER)"
            : "HOUR(DATE_ADD(checked_in_at, INTERVAL {$offset} SECOND))";
        $peak = (clone $query)->selectRaw($hour.' as hour, COUNT(*) as total')->groupByRaw($hour)->orderByDesc('total')->orderBy('hour')->first();
        $peakTime = $peak ? $start->startOfDay()->addHours((int) $peak->hour)->format('g A').' – '.$start->startOfDay()->addHours((int) $peak->hour + 1)->format('g A') : null;

        return Inertia::render('dashboard/attendance/History', [
            'filters' => ['view' => $view, 'date' => $date, 'branch_id' => $branchId ? (int) $branchId : null, 'search' => $search, 'source' => $filters['source'] ?? '', 'per_page' => (int) ($filters['per_page'] ?? 5)],
            'canExport' => $canExport,
            'defaultBranchId' => app(CurrentGym::class)->viewBranchId() ?? Branch::where('is_main', true)->value('id'),
            'insights' => ['dailyAverage' => round($summary->check_ins / max(1, $start->diffInDays($end)), 1), 'peakTime' => $peakTime],
            'period' => ['from' => $start->format('M j, Y'), 'through' => $end->subDay()->format('M j, Y')],
            'summary' => ['checkIns' => (int) $summary->check_ins, 'uniqueMembers' => (int) $summary->unique_members],
            'branches' => Branch::orderByDesc('is_main')->orderBy('name')->get(['id', 'name'])->map(fn ($branch) => $branch->only(['id', 'name'])),
            'attendance' => $query->with(['member:id,name,mobile', 'branch:id,name', 'recorder:id,name'])->orderByDesc('checked_in_at')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 5))->onEachSide(1)->withQueryString()->through(fn ($record) => [
                'createdAt' => $record->created_at->setTimezone($timezone)->format('M j, Y g:i A'), 'createdBy' => $record->recorder?->name ?? __('Not recorded'), 'id' => $record->id,
                'memberName' => $record->member?->name ?? __('Removed member'),
                'memberMobile' => $record->member?->mobile ?? '',
                'memberInitials' => $record->member ? collect(explode(' ', trim($record->member->name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') : '?',
                'branchName' => $record->branch?->name ?? __('Removed branch'),
                'checkedInAt' => $record->checked_in_at->setTimezone($timezone)->format('M j, Y g:i A'),
                'source' => $record->source === 'qr' ? 'QR scan' : 'Manual',
            ]),
        ]);
    }
}
