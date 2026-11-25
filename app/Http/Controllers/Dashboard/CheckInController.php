<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\CheckInMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Member;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('create', AttendanceRecord::class);

        $context = app(CurrentGym::class);

        return Inertia::render('dashboard/attendance/CheckIn', [
            'branches' => Branch::orderByDesc('is_main')->orderBy('name')->get(['id', 'name'])->map(fn ($branch) => $branch->only(['id', 'name'])),
            'defaultBranchId' => $context->viewBranchId() ?? Branch::where('is_main', true)->value('id'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        Gate::authorize('create', AttendanceRecord::class);
        $data = $request->validate(['search' => ['nullable', 'string', 'min:2', 'max:100'], 'branch_id' => ['nullable', 'integer', 'min:1']]);
        $context = app(CurrentGym::class);
        $timezone = $context->get()->timezone;
        $branch = Branch::findOrFail($data['branch_id'] ?? $context->branchId() ?? $context->viewBranchId() ?? Branch::where('is_main', true)->value('id'));
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $checkedIn = AttendanceRecord::where('branch_id', $branch->id)
            ->where('checked_in_at', '>=', $today->utc())
            ->where('checked_in_at', '<', $today->addDay()->utc())
            ->select('member_id');
        $search = trim($data['search'] ?? '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
        // Attendance accepts visiting members; the destination branch was authorized above.
        $members = $context->runAs($context->get(), fn () => Member::query()
            ->whereNotIn('id', $checkedIn)
            ->when($search === '', fn ($query) => $query->where('is_active', true))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("mobile LIKE ? ESCAPE '!'", [$pattern])->when(ctype_digit($search), fn ($match) => $match->orWhere('id', $search))))
            ->with(['currentMembership', 'nextMembership', 'latestMembership'])->orderByRaw('LOWER(name)')->orderBy('id')->paginate(25));

        return response()->json(['members' => $members->through(function ($member) use ($timezone) {
            $membership = $member->currentMembership ?? $member->nextMembership ?? $member->latestMembership;

            return [
                ...$member->only(['id', 'name', 'is_active']),
                'mobileHint' => $member->mobile ? '…'.substr($member->mobile, -4) : '',
                'membershipStatus' => $membership?->displayStatus(null, $timezone) ?? 'No membership',
                'membershipSummary' => $membership ? '₱'.number_format($membership->price_centavos / 100, 2).' / '.$membership->duration_amount.' '.$membership->duration_unit : null,
            ];
        })])->header('Cache-Control', 'no-store, private');
    }

    public function store(CheckInRequest $request, CheckInMember $action): JsonResponse
    {
        $result = $action->handle($request->validated());
        $record = $result['record'];

        return response()->json([
            'duplicate' => $result['duplicate'],
            'memberName' => $record->member->name,
            'checkedInAt' => $record->checked_in_at->setTimezone(app(CurrentGym::class)->get()->timezone)->format('M j, Y g:i A'),
        ])->header('Cache-Control', 'no-store, private');
    }
}
