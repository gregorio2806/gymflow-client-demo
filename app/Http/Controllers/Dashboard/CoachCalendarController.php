<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateCoachSchedule;
use App\Actions\ManageCoachSchedule;
use App\Enums\GymRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CancelCoachScheduleRequest;
use App\Http\Requests\Dashboard\CoachCalendarRequest;
use App\Http\Requests\Dashboard\StoreCoachScheduleRequest;
use App\Http\Requests\Dashboard\UpdateCoachScheduleRequest;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Support\Audit;
use App\Support\CoachBookingAvailability;
use App\Support\CoachCalendarAccess;
use App\Support\CoachCalendarMonth;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CoachCalendarController extends Controller
{
    public function roster(Request $request): Response
    {
        $coaches = CoachCalendarAccess::coaches($request->user())
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($coach) => ['id' => $coach->id, 'name' => $coach->name]);

        return Inertia::render('dashboard/coach-calendar/Coaches', [
            'coaches' => $coaches,
        ]);
    }

    public function index(CoachCalendarRequest $request, CoachCalendarMonth $calendar): Response
    {
        $data = $request->validated();
        $gym = app(CurrentGym::class)->get();
        $today = CarbonImmutable::now($gym->timezone);
        $month = isset($data['month']) ? CarbonImmutable::createFromFormat('!Y-m', $data['month'], $gym->timezone) : $today->startOfMonth();
        $selected = $data['day'] ?? ($month->isSameMonth($today) ? $today->toDateString() : $month->toDateString());
        if (substr($selected, 0, 7) !== $month->format('Y-m')) {
            throw ValidationException::withMessages(['day' => 'Choose a day in the displayed month.']);
        }
        $coaches = CoachCalendarAccess::coaches($request->user());
        $coach = isset($data['coach_id']) ? (clone $coaches)->whereKey($data['coach_id'])->firstOrFail(['users.id', 'users.name']) : (clone $coaches)->orderBy('name')->orderBy('id')->first(['users.id', 'users.name']);
        $filters = ['month' => $month->format('Y-m'), 'day' => $selected, 'view' => $data['view'] ?? 'month', ...($coach ? ['coach_id' => $coach->id] : [])];
        $start = CarbonImmutable::parse($selected, $gym->timezone)->startOfDay();
        // Actor/coach permissions were rechecked above; scoped rows and eager-loaded branch state avoid per-row policy queries.
        $schedules = $coach ? app(CurrentGym::class)->filterView(CoachSchedule::where('coach_id', $coach->id))->overlapping($start->utc(), $start->addDay()->utc())->with(['branch:id,name,is_active', 'creator:id,name', 'member:id,name'])->orderBy('starts_at')->orderBy('id')->paginate(10, ['id', 'coach_id', 'branch_id', 'title', 'notes', 'starts_at', 'ends_at', 'is_cancelled', 'revision', 'created_at', 'created_by', 'kind', 'member_id', 'customer_name', 'service', 'location', 'status'], 'schedules_page')->withQueryString()->appends($filters)->through(fn ($schedule) => [
            ...$schedule->only(['id', 'title', 'is_cancelled', 'revision']),
            ...$schedule->only(['kind', 'service', 'location', 'status']),
            'customer' => $schedule->member?->name ?? $schedule->customer_name,
            'createdAt' => $schedule->created_at->setTimezone($gym->timezone)->format('M j, Y g:i A'), 'createdBy' => $schedule->creator?->name ?? __('Not recorded'),
            'branch' => $schedule->branch->name,
            'startsAt' => $schedule->starts_at->setTimezone($gym->timezone)->format('M j, Y g:i A'),
            'endsAt' => $schedule->ends_at->setTimezone($gym->timezone)->format('M j, Y g:i A'),
            'startTime' => $schedule->starts_at->setTimezone($gym->timezone)->format('g:i A'),
            'endTime' => $schedule->ends_at->setTimezone($gym->timezone)->format('g:i A'),
            'dateRange' => $schedule->starts_at->setTimezone($gym->timezone)->format('F j, Y').($schedule->starts_at->setTimezone($gym->timezone)->isSameDay($schedule->ends_at->setTimezone($gym->timezone)) ? '' : ' - '.$schedule->ends_at->setTimezone($gym->timezone)->format('F j, Y')),
            'canEdit' => $schedule->kind === 'block' && ! $schedule->is_cancelled && $schedule->branch->is_active,
            'canCancel' => $schedule->kind === 'block' && ! $schedule->is_cancelled && $schedule->branch->is_active,
            'edit' => $schedule->kind === 'block' && ! $schedule->is_cancelled && $schedule->branch->is_active ? [
                ...$schedule->only(['branch_id', 'notes', 'revision']),
                'starts_at' => $schedule->starts_at->setTimezone($gym->timezone)->format('Y-m-d\TH:i'),
                'ends_at' => $schedule->ends_at->setTimezone($gym->timezone)->format('Y-m-d\TH:i'),
            ] : null,
        ]) : null;
        $branches = Branch::where('is_active', true)->orderBy('name')->orderBy('id')->paginate(25, ['id', 'name'], 'branches_page')->withQueryString()->appends($filters)->through(fn ($branch) => $branch->only(['id', 'name']));

        return Inertia::render('dashboard/coach-calendar/Index', [
            'coaches' => $coaches->orderBy('name')->orderBy('id')->paginate(25, ['users.id', 'users.name'])->withQueryString()->appends($filters)->through(fn ($coach) => ['id' => $coach->id, 'name' => $coach->name]),
            'selectedCoach' => $coach ? ['id' => $coach->id, 'name' => $coach->name] : null,
            'canSelectCoach' => $request->user()->roleIn($gym) !== GymRole::Coach,
            'month' => ['value' => $month->format('Y-m'), 'label' => $month->format('F Y'), 'offset' => $month->dayOfWeek, 'previous' => $month->format('Y-m') === '1000-01' ? null : $month->subMonth()->format('Y-m'), 'next' => $month->format('Y-m') === '9998-12' ? null : $month->addMonth()->format('Y-m')],
            'today' => $today->toDateString(), 'selectedDay' => $selected,
            'days' => $coach ? $calendar->build($coach->id, $filters['view'] === 'week' ? $start->startOfWeek(0) : $month, $selected, $today->toDateString(), $filters['view'] === 'week' ? 7 : null) : [],
            'view' => $filters['view'],
            'availability' => $coach ? CoachBookingAvailability::hours($coach->id) : [],
            'availabilityRevision' => $coach ? CoachBookingAvailability::membership($coach->id)->availability_revision : 1,
            'canEditAvailability' => $coach !== null && CoachBookingAvailability::canEdit($request->user()),
            'schedules' => $schedules,
            'branches' => $branches,
            'canAdd' => $coach !== null && $branches->total() > 0,
            'requestKey' => (string) Str::uuid(),
            'timezone' => $gym->timezone,
        ]);
    }

    public function store(StoreCoachScheduleRequest $request, CreateCoachSchedule $action): RedirectResponse
    {
        $data = $request->validated();
        $action->handle($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Schedule added.']);

        return to_route('coach-calendar.index', ['coach_id' => $data['coach_id'], 'month' => $data['month'], 'day' => $data['day']]);
    }

    public function update(UpdateCoachScheduleRequest $request, CoachSchedule $schedule, ManageCoachSchedule $action): RedirectResponse
    {
        abort_unless($schedule->kind === 'block', 404);
        $data = $request->validated();
        $schedule = $action->update($schedule, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Schedule saved.']);

        return to_route('coach-calendar.index', ['coach_id' => $schedule->coach_id, 'month' => $data['month'], 'day' => $data['day']]);
    }

    public function cancel(CancelCoachScheduleRequest $request, CoachSchedule $schedule, ManageCoachSchedule $action): RedirectResponse
    {
        abort_unless($schedule->kind === 'block', 404);
        $data = $request->validated();
        $schedule = $action->cancel($schedule, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Schedule cancelled. History is kept.']);

        return to_route('coach-calendar.index', ['coach_id' => $schedule->coach_id, 'month' => $data['month'], 'day' => $data['day']]);
    }

    public function availability(Request $request, int $coach): RedirectResponse
    {
        Gate::authorize('viewAny', CoachSchedule::class);
        abort_unless(CoachBookingAvailability::canEdit($request->user()), 403);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'hours' => ['required', 'array', 'size:7'], 'hours.*' => ['required', 'array:enabled,start,end'], 'hours.*.enabled' => ['required', 'boolean'], 'hours.*.start' => ['required', 'date_format:H:i'], 'hours.*.end' => ['required', 'date_format:H:i']]);
        if (array_keys($data['hours']) !== range(0, 6)) {
            throw ValidationException::withMessages(['hours' => 'Provide all seven weekdays.']);
        }
        foreach ($data['hours'] as $i => $hours) {
            if ($hours['enabled'] && $hours['end'] <= $hours['start']) {
                throw ValidationException::withMessages(["hours.$i.end" => 'End time must be after start time.']);
            }
        }
        DB::transaction(function () use ($request, $coach, $data) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            CoachCalendarAccess::coaches($request->user())->lockForUpdate()->findOrFail($coach);
            $pivot = CoachBookingAvailability::membership($coach);
            if ($pivot->availability_revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Availability changed. Refresh before saving again.']);
            }
            $gym->users()->updateExistingPivot($coach, ['availability' => $data['hours'], 'availability_revision' => $pivot->availability_revision + 1]);
            Audit::log('coach.availability-updated', $gym, ['coach_id' => $coach, 'before' => $pivot->availability, 'after' => $data['hours']]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coach availability saved. Existing bookings are retained.']);

        return back();
    }
}
