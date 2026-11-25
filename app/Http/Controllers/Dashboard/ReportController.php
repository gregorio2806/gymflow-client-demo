<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\RequestReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReportRequest;
use App\Models\Branch;
use App\Models\ReportExport;
use App\Support\Audit;
use App\Support\DetailedGymReport;
use App\Support\GymReport;
use App\Tenancy\CurrentGym;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Pennant\Feature;

class ReportController extends Controller
{
    public function index(ReportRequest $request, GymReport $report, DetailedGymReport $details)
    {
        $filters = $report->filters($request->validated());
        $data = $report->build($filters);
        $full = Feature::active('full-reports');
        $available = [];
        foreach (['revenue' => 'payments', 'membership' => 'members', 'attendance' => 'qr-attendance', 'appointments' => 'coach-calendar'] as $key => $feature) {
            if (Feature::active($feature)) {
                $available[] = $key;
            }
        }
        $type = $request->validated('report') ?? ($available[0] ?? 'revenue');
        $type = $type === 'payments' ? 'membership' : $type;
        $type = $type === 'exports' ? 'revenue' : $type;
        abort_unless(in_array($type, $available, true), 404);
        $filters = [...$filters, 'period' => $request->validated('period'), 'month' => $request->validated('month') ?? substr($filters['from'], 0, 7), 'report' => $type, 'kind' => $request->validated('kind') ?? '', 'payment_state' => $request->validated('payment_state') ?? '', 'search' => $type === 'revenue' ? '' : trim($request->validated('search') ?? ''), 'source' => $request->validated('source') ?? '', 'method' => $type === 'revenue' ? '' : ($request->validated('method') ?? ''), 'status' => $request->validated('status') ?? '', 'per_page' => (int) ($request->validated('per_page') ?? 10), 'days_sort' => $request->validated('days_sort') ?? 'desc'];
        if ($request->validated('export') === 'csv') {
            abort_unless($full && $type !== 'exports', 404);

            return $details->csv($type, $filters);
        }
        $page = max(1, min(25, (int) $request->input('page', 1)));
        $daysPage = max(1, min(25, (int) $request->input('days_page', 1)));
        $days = $filters['days_sort'] === 'asc' ? $data['days'] : array_reverse($data['days']);

        return Inertia::render('dashboard/reports/Index', ['filters' => $filters, 'summary' => $data['summary'], 'fullReports' => $full, 'requestKey' => (string) Str::uuid(), 'availableReports' => $available, 'detail' => $type === 'exports' ? null : $details->build($type, $filters, $request->url()),
            'days' => new LengthAwarePaginator(array_slice($days, ($daysPage - 1) * 10, 10), count($days), 10, $daysPage, ['path' => $request->url(), 'query' => $filters, 'pageName' => 'days_page']),
            'branches' => Branch::orderBy('name')->orderBy('id')->paginate(25, ['id', 'name'], 'branches_page')->withQueryString()->through(fn ($b) => $b->only(['id', 'name'])),
            'exports' => $full ? ReportExport::where('user_id', auth()->id())->where('requested_role', app(CurrentGym::class)->role()->value)->when(app(CurrentGym::class)->viewBranchId(), fn ($q) => $q->where('branch_id', app(CurrentGym::class)->viewBranchId()))->where('expires_at', '>', now()->utc())->latest('id')->paginate(10, ['id', 'status', 'from', 'through', 'expires_at', 'created_at'], 'exports_page')->withQueryString()->through(fn ($e) => ['id' => $e->id, 'status' => $e->status, 'from' => $e->from, 'through' => $e->through, 'createdAt' => $e->created_at->setTimezone(app(CurrentGym::class)->get()->timezone)->format('M j, Y g:i A'), 'createdBy' => $request->user()->name]) : null]);
    }

    public function store(ReportRequest $request, GymReport $report, RequestReportExport $action)
    {
        $filters = $report->filters($request->validated());
        $action->handle($filters, $request->validated('request_key'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Spreadsheet queued. Refresh export status on Revenue when it is ready.']);

        return to_route('reports.index', [...$filters, 'period' => $request->validated('period'), 'month' => $request->validated('month'), 'report' => 'revenue']);
    }

    public function download(ReportExport $export)
    {
        abort_unless($export->user_id === auth()->id(), 403);
        Gate::authorize('view', $export);
        abort_unless($export->status === 'ready', 409);
        Audit::log('report-export.downloaded', $export, ['after' => ['export_id' => $export->id]]);

        return response($export->csv)->header('Content-Type', 'text/csv; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="gym-report-'.$export->from.'-'.$export->through.'.csv"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
