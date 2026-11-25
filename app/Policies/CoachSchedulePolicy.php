<?php

namespace App\Policies;

use App\Enums\GymRole;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\User;
use App\Support\CoachCalendarAccess;
use App\Tenancy\CurrentGym;
use Laravel\Pennant\Feature;

class CoachSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentGym::class)->get() !== null && Feature::active('coach-calendar') && CoachCalendarAccess::actor($user) !== null;
    }

    public function view(User $user, CoachSchedule $schedule): bool
    {
        if (! $this->viewAny($user) || $schedule->gym_id !== app(CurrentGym::class)->id()) {
            return false;
        }
        $actor = CoachCalendarAccess::actor($user);

        return match ($actor->pivot->role) {
            GymRole::Owner, GymRole::Staff => true,
            GymRole::Coach => $schedule->coach_id === $user->id,
            GymRole::BranchManager => $schedule->branch_id === $actor->pivot->branch_id && CoachCalendarAccess::coaches($user)->whereKey($schedule->coach_id)->exists(),
            default => false,
        };
    }

    public function create(User $user, int $coachId, int $branchId): bool
    {
        if (! $this->viewAny($user) || ! CoachCalendarAccess::coaches($user)->whereKey($coachId)->exists()) {
            return false;
        }
        $branch = Branch::withoutGlobalScopes()->where('gym_id', app(CurrentGym::class)->id())->whereKey($branchId)->where('is_active', true)->first();
        $actor = CoachCalendarAccess::actor($user);

        return $branch !== null && ($actor->pivot->role !== GymRole::BranchManager || $branchId === $actor->pivot->branch_id);
    }

    public function update(User $user, CoachSchedule $schedule): bool
    {
        return ! $schedule->is_cancelled && ($schedule->kind !== 'appointment' || $schedule->status === 'scheduled') && $this->cancel($user, $schedule);
    }

    public function cancel(User $user, CoachSchedule $schedule): bool
    {
        return ($schedule->kind !== 'appointment' || CoachCalendarAccess::actor($user)?->pivot->role !== GymRole::Coach) && $this->view($user, $schedule) && $this->create($user, $schedule->coach_id, $schedule->branch_id);
    }
}
