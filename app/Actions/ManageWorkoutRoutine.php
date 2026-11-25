<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberRoutineAssignment;
use App\Models\WorkoutRoutine;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Support\WorkoutAccess;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageWorkoutRoutine
{
    public function save(array $data, ?WorkoutRoutine $routine = null): WorkoutRoutine
    {
        return DB::transaction(function () use ($data, $routine) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $gym->subscription()->lockForUpdate()->firstOrFail();
            $days = array_map(fn ($day) => ['name' => $day['name'], 'exercises' => array_map(fn ($exercise) => ['name' => $exercise['name'], 'sets' => (int) $exercise['sets'], 'reps' => $exercise['reps'], 'notes' => $exercise['notes'] ?? ''], $day['exercises'])], $data['days']);
            $content = ['name' => $data['name'], 'notes' => $data['notes'] ?? '', 'days' => $days];
            if ($routine) {
                $routine = WorkoutRoutine::lockForUpdate()->findOrFail($routine->id);
                Gate::authorize('update', $routine);
                if ($routine->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['revision' => 'This routine changed. Reload before saving.']);
                }
                if ($routine->branch_id !== (int) $data['branch_id']) {
                    throw ValidationException::withMessages(['branch_id' => 'A routine cannot change branches. Create a new routine for that branch.']);
                }
            } else {
                Gate::authorize('create', WorkoutRoutine::class);
                $branch = Branch::findOrFail($data['branch_id']);
                if (app(CurrentGym::class)->role() === GymRole::Coach && ! in_array($branch->id, WorkoutAccess::coachBranchIds(), true)) {
                    abort(403);
                }
                $existing = WorkoutRoutine::withoutGlobalScope('workout-access')->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    Gate::authorize('update', $existing);
                    if ($existing->branch_id !== $branch->id || $existing->name !== $content['name'] || ($existing->notes ?? '') !== $content['notes'] || $existing->days !== $content['days']) {
                        throw ValidationException::withMessages(['request_key' => 'This submission was already used for another routine.']);
                    }

                    return $existing;
                }
                $routine = (new WorkoutRoutine)->forceFill(['branch_id' => $branch->id, 'created_by' => auth()->id(), 'request_key' => $data['request_key'], 'revision' => 0]);
            }
            app(PlanLimits::class)->ensureBranchWritable($gym, $routine->branch_id);
            $routine->forceFill([...$content, 'revision' => $routine->revision + 1])->save();
            Audit::log('workout-routine.saved', $routine, ['after' => ['routine_id' => $routine->id, 'revision' => $routine->revision]]);

            return $routine;
        });
    }

    public function assign(WorkoutRoutine $routine, int $memberId, int $revision): void
    {
        DB::transaction(function () use ($routine, $memberId, $revision) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $gym->subscription()->lockForUpdate()->firstOrFail();
            $routine = WorkoutRoutine::lockForUpdate()->findOrFail($routine->id);
            Gate::authorize('update', $routine);
            if ($routine->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'This routine changed. Reload before assigning.']);
            }
            $member = Member::where('home_branch_id', $routine->branch_id)->lockForUpdate()->findOrFail($memberId);
            if (! $member->is_active) {
                throw ValidationException::withMessages(['member_id' => 'Choose an active member profile.']);
            }
            app(PlanLimits::class)->ensureBranchWritable($gym, $routine->branch_id);
            // Current home branch authorizes replacement; history from a previous branch is preserved.
            $active = MemberRoutineAssignment::withoutGlobalScope('branch')->where('member_id', $member->id)->whereNull('ended_at')->lockForUpdate()->get();
            if ($active->count() === 1 && $active->first()->workout_routine_id === $routine->id && $active->first()->routine_revision === $revision) {
                return;
            }
            foreach ($active as $old) {
                $old->ended_at = now()->utc();
                $old->save();
            }
            $assignment = (new MemberRoutineAssignment)->forceFill(['branch_id' => $routine->branch_id, 'member_id' => $member->id, 'workout_routine_id' => $routine->id, 'assigned_by' => auth()->id(), 'assigned_at' => now()->utc(), 'routine_revision' => $revision, 'snapshot' => $routine->only(['name', 'notes', 'days'])]);
            $assignment->save();
            Audit::log('workout-routine.assigned', $assignment, ['after' => ['routine_id' => $routine->id, 'member_id' => $member->id, 'revision' => $revision]]);
        });
    }
}
