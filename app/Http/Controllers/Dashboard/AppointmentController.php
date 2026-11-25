<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateCoachSchedule;
use App\Actions\ManageCoachSchedule;
use App\Enums\AppointmentService;
use App\Enums\GymRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveAppointmentRequest;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Member;
use App\Support\CoachBookingAvailability;
use App\Support\CoachCalendarAccess;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', CoachSchedule::class);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'], 'coach_id' => ['nullable', 'integer', 'min:1'], 'branch_id' => ['nullable', 'integer', 'min:1'], 'service' => ['nullable', Rule::enum(AppointmentService::class)], 'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'no-show'])], 'appointment' => ['nullable', 'integer', 'min:1']]);
        $gym = app(CurrentGym::class)->get();
        $today = CarbonImmutable::now($gym->timezone)->startOfDay();
        $filters = ['search' => $data['search'] ?? '', 'date' => $request->has('date') ? ($data['date'] ?? '') : $today->toDateString(), 'coach_id' => $data['coach_id'] ?? '', 'branch_id' => $data['branch_id'] ?? '', 'service' => $data['service'] ?? '', 'status' => $data['status'] ?? ''];
        $coaches = CoachCalendarAccess::coaches($request->user());
        if ($filters['coach_id']) {
            (clone $coaches)->findOrFail($filters['coach_id']);
        }
        if ($filters['branch_id']) {
            Branch::findOrFail($filters['branch_id']);
        }
        $canManage = CoachCalendarAccess::actor($request->user())->pivot->role !== GymRole::Coach;
        $query = app(CurrentGym::class)->filterView(CoachSchedule::where('kind', 'appointment'));
        $metrics = (clone $query)->selectRaw('SUM(CASE WHEN starts_at >= ? AND starts_at < ? THEN 1 ELSE 0 END) as today, SUM(CASE WHEN status = ? AND starts_at >= ? THEN 1 ELSE 0 END) as upcoming, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as missed', [$today->utc(), $today->addDay()->utc(), 'scheduled', now()->utc(), 'completed', 'cancelled', 'no-show'])->toBase()->first();
        $query->when($filters['date'], function ($q, $date) use ($gym) {
            $day = CarbonImmutable::parse($date, $gym->timezone);
            $q->where('starts_at', '>=', $day->utc())->where('starts_at', '<', $day->addDay()->utc());
        });
        foreach (['coach_id', 'branch_id', 'service', 'status'] as $field) {
            $query->when($filters[$field], fn ($q, $value) => $q->where($field, $value));
        }
        $query->when($filters['search'], function ($q, $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $q->where(fn ($match) => $match->whereRaw("customer_name LIKE ? ESCAPE '!'", [$pattern])->orWhereHas('member', fn ($m) => $m->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])));
        });
        $relations = ['member:id,name', 'coach:id,name', 'branch:id,name,is_active', 'creator:id,name', 'package:id,plan_name'];
        $map = function ($row) use ($gym, $canManage) {
            return [...$row->only(['id', 'member_id', 'member_purchase_id', 'customer_name', 'coach_id', 'branch_id', 'service', 'location', 'status', 'notes', 'revision']), 'customer' => $row->member?->name ?? $row->customer_name, 'serviceLabel' => AppointmentService::from($row->service)->label(), 'packageName' => $row->package?->plan_name, 'coach' => $row->coach?->name ?? 'Removed coach', 'branch' => $row->branch?->name ?? 'Removed branch', 'date' => $row->starts_at->setTimezone($gym->timezone)->format('F j, Y'), 'time' => $row->starts_at->setTimezone($gym->timezone)->format('g:i A').' – '.$row->ends_at->setTimezone($gym->timezone)->format('g:i A'), 'starts_at' => $row->starts_at->setTimezone($gym->timezone)->format('Y-m-d\TH:i'), 'ends_at' => $row->ends_at->setTimezone($gym->timezone)->format('Y-m-d\TH:i'), 'canManage' => $canManage && $row->status === 'scheduled' && $row->branch?->is_active, 'hasEnded' => $row->ends_at->isPast(), 'createdAt' => $row->created_at->setTimezone($gym->timezone)->format('F j, Y g:i A'), 'createdBy' => $row->creator?->name ?? 'Not recorded'];
        };
        $selected = isset($data['appointment']) ? CoachSchedule::where('kind', 'appointment')->with($relations)->findOrFail($data['appointment']) : null;

        return Inertia::render('dashboard/appointments/Index', ['filters' => $filters, 'appointments' => $query->with($relations)->orderBy('starts_at')->orderBy('id')->paginate(25)->withQueryString()->through($map), 'selected' => $selected ? $map($selected) : null, 'metrics' => array_map(fn ($value) => (int) $value, (array) $metrics), 'coaches' => $coaches->orderBy('name')->paginate(25, ['users.id', 'users.name'], 'coaches_page')->withQueryString()->through(fn ($c) => $c->only(['id', 'name'])), 'branches' => Branch::where('is_active', true)->orderBy('name')->paginate(25, ['id', 'name'], 'branches_page')->withQueryString()->through(fn ($b) => $b->only(['id', 'name'])), 'services' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], AppointmentService::cases()), 'canManage' => $canManage, 'today' => $today->toDateString(), 'requestKey' => (string) Str::uuid(), 'timezone' => $gym->timezone]);
    }

    public function store(SaveAppointmentRequest $request, CreateCoachSchedule $action)
    {
        $action->handle([...$request->validated(), 'kind' => 'appointment', 'title' => 'Appointment']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment booked.']);

        return to_route('appointments.index', ['date' => substr($request->starts_at, 0, 10)]);
    }

    public function update(SaveAppointmentRequest $request, CoachSchedule $schedule, ManageCoachSchedule $action)
    {
        abort_unless($schedule->kind === 'appointment', 404);
        $action->update($schedule, [...$request->validated(), 'title' => 'Appointment']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment saved.']);

        return to_route('appointments.index', ['date' => substr($request->starts_at, 0, 10)]);
    }

    public function status(Request $request, CoachSchedule $schedule, ManageCoachSchedule $action)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(['completed', 'cancelled', 'no-show'])]]);
        $action->status($schedule, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment status updated.']);

        return back();
    }

    public function members(Request $request)
    {
        Gate::authorize('viewAny', CoachSchedule::class);
        abort_if(CoachCalendarAccess::actor($request->user())->pivot->role === GymRole::Coach, 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'coach_id' => ['required', 'integer'], 'branch_id' => ['required', 'integer']]);
        Gate::authorize('create', [CoachSchedule::class, (int) $data['coach_id'], (int) $data['branch_id']]);
        $term = $data['search'] ?? '';
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $members = Member::withoutGlobalScope('branch')->where('is_active', true)->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("mobile LIKE ? ESCAPE '!'", [$pattern])->when(ctype_digit($term), fn ($q) => $q->orWhere('id', (int) $term)))->with(['purchases' => fn ($q) => $q->where('kind', 'package')->where('status', 'active')->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()->utc()))])->orderBy('name')->limit(25)->get(['id', 'name']);

        return response()->json(['members' => $members->map(fn ($m) => [...$m->only(['id', 'name']), 'packages' => $m->purchases->filter(fn ($p) => $p->remaining() > 0 && (empty($p->snapshot['branch_ids']) || in_array((int) $data['branch_id'], $p->snapshot['branch_ids'])) && (empty($p->snapshot['configuration']['coach_ids']) || in_array((int) $data['coach_id'], $p->snapshot['configuration']['coach_ids'])))->take(50)->values()->map(fn ($p) => ['id' => $p->id, 'name' => $p->plan_name, 'remaining' => $p->remaining()])])])->header('Cache-Control', 'private, no-store');
    }

    public function slots(Request $request)
    {
        Gate::authorize('viewAny', CoachSchedule::class);
        $data = $request->validate(['coach_id' => ['required', 'integer'], 'branch_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'], 'duration' => ['required', 'integer', 'min:15', 'max:480'], 'except' => ['nullable', 'integer']]);
        Gate::authorize('create', [CoachSchedule::class, (int) $data['coach_id'], (int) $data['branch_id']]);
        if (! empty($data['except'])) {
            $schedule = CoachSchedule::where('kind', 'appointment')->findOrFail($data['except']);
            Gate::authorize('update', $schedule);
        }

        return response()->json(['slots' => CoachBookingAvailability::slots((int) $data['coach_id'], (int) $data['branch_id'], $data['date'], (int) $data['duration'], $data['except'] ?? null)])->header('Cache-Control', 'private, no-store');
    }
}
