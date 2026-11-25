<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\ManageWorkoutRoutine;
use App\Enums\GymRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AssignWorkoutRoutineRequest;
use App\Http\Requests\Dashboard\SaveWorkoutRoutineRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberRoutineAssignment;
use App\Models\WorkoutRoutine;
use App\Support\WorkoutAccess;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutRoutineController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', WorkoutRoutine::class);

        return Inertia::render('dashboard/workouts/Index', ['routines' => app(CurrentGym::class)->filterView(WorkoutRoutine::with('branch'))->orderBy('name')->orderBy('id')->paginate(25)->withQueryString()->through(fn ($routine) => [...$routine->only(['id', 'name', 'revision']), 'branch' => $routine->branch->name]), 'canCreate' => Gate::allows('create', WorkoutRoutine::class)]);
    }

    public function create(): Response
    {
        Gate::authorize('create', WorkoutRoutine::class);
        $branches = Branch::when(app(CurrentGym::class)->role() === GymRole::Coach, fn ($query) => $query->whereIn('id', WorkoutAccess::coachBranchIds()))->orderBy('name')->orderBy('id')->paginate(25, ['*'], 'branches_page')->withQueryString()->through(fn ($branch) => $branch->only(['id', 'name', 'is_active']));

        return Inertia::render('dashboard/workouts/Edit', ['routine' => null, 'branches' => $branches, 'members' => null, 'assignments' => null, 'search' => '', 'requestKey' => (string) Str::uuid(), 'writable' => true]);
    }

    public function edit(Request $request, WorkoutRoutine $routine): Response
    {
        Gate::authorize('update', $routine);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';
        $members = Member::where('home_branch_id', $routine->branch_id)->where('is_active', true)->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))->orderBy('name')->orderBy('id')->paginate(25, ['id', 'name'], 'members_page')->withQueryString()->through(fn ($member) => $member->only(['id', 'name']));
        $assignments = MemberRoutineAssignment::where('workout_routine_id', $routine->id)->whereNull('ended_at')->whereHas('member', fn ($query) => $query->where('home_branch_id', $routine->branch_id))->with(['member', 'assigner:id,name'])->orderByDesc('id')->paginate(10, ['*'], 'assignments_page')->withQueryString()->through(fn ($assignment) => ['createdAt' => $assignment->created_at->setTimezone(app(CurrentGym::class)->get()->timezone)->format('M j, Y g:i A'), 'createdBy' => $assignment->assigner?->name ?? __('Not recorded'), 'id' => $assignment->id, 'member' => $assignment->member->name, 'revision' => $assignment->routine_revision, 'assignedAt' => $assignment->assigned_at->setTimezone(app(CurrentGym::class)->get()->timezone)->format('M j, Y g:i A')]);

        return Inertia::render('dashboard/workouts/Edit', ['routine' => [...$routine->only(['id', 'name', 'notes', 'days', 'branch_id', 'revision']), 'branch' => $routine->branch->name], 'branches' => null, 'members' => $members, 'assignments' => $assignments, 'search' => $search, 'requestKey' => null, 'writable' => $routine->branch->is_active]);
    }

    public function store(SaveWorkoutRoutineRequest $request, ManageWorkoutRoutine $action): RedirectResponse
    {
        $routine = $action->save($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Routine saved. You can now assign it to members.']);

        return to_route('workout-routines.edit', $routine);
    }

    public function update(SaveWorkoutRoutineRequest $request, WorkoutRoutine $routine, ManageWorkoutRoutine $action): RedirectResponse
    {
        $action->save($request->validated(), $routine);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Routine saved. Existing assignments keep their saved version; reassign to update them.']);

        return to_route('workout-routines.edit', $routine);
    }

    public function assign(AssignWorkoutRoutineRequest $request, WorkoutRoutine $routine, ManageWorkoutRoutine $action): RedirectResponse
    {
        $action->assign($routine, (int) $request->validated('member_id'), (int) $request->validated('revision'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Routine assigned to member.']);

        return to_route('workout-routines.edit', $routine);
    }
}
