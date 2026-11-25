<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Member;
use App\Models\Payment;
use App\Support\DashboardAnalytics;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardAnalytics $analytics): Response|RedirectResponse
    {
        if (! app(CurrentGym::class)->check()) {
            return to_route($request->user()->is_platform_admin ? 'platform.gyms.index' : 'gyms.create');
        }
        $period = $request->validate(['period' => ['nullable', Rule::in(['week', 'month', 'year'])]])['period'] ?? 'month';
        $counts = null;
        if (Gate::allows('viewAny', Member::class) && Feature::active('members')) {
            $at = CarbonImmutable::now();
            $cutoff = $at->setTimezone(app(CurrentGym::class)->get()->timezone)->startOfDay()->addDays(8)->utc();
            // Count profiles, not historical periods. Expiring memberships are also active.
            $members = app(CurrentGym::class)->filterView(Member::query(), 'home_branch_id');
            $active = (clone $members)->whereHas('currentMembership', fn ($query) => $query->where('status', 'active'));
            $counts = [
                'active' => (clone $active)->count(),
                'expiringThisWeek' => (clone $members)->whereHas('currentMembership', fn ($query) => $query->where('status', 'active')->where('ends_at', '<=', $cutoff))->count(),
                'expired' => (clone $members)->whereDoesntHave('currentMembership')->whereDoesntHave('nextMembership')
                    ->whereHas('latestMembership', fn ($query) => $query->where('status', '!=', 'cancelled')
                        ->where(fn ($query) => $query->where('status', 'expired')->orWhere('ends_at', '<=', $at)))->count(),
            ];
        }

        return Inertia::render('Dashboard', ['membershipCounts' => $counts,
            'analytics' => $analytics->build($period, $counts,
                Gate::allows('viewAny', Payment::class) && Feature::active('payments'),
                Gate::allows('viewAny', AttendanceRecord::class) && Feature::active('qr-attendance'))]);
    }
}
