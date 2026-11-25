<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Support\Audit;
use App\Support\CoachBookingAvailability;
use App\Support\CoachCalendarAccess;
use App\Support\PlanLimits;
use App\Support\TrainingPackage;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageCoachSchedule
{
    private function lockedGym(): Gym
    {
        $context = app(CurrentGym::class);
        $gym = Gym::whereKey($context->id())->lockForUpdate()->firstOrFail();
        $gym->setRelation('subscription', $gym->subscription()->lockForUpdate()->firstOrFail());
        $context->set($gym, $context->role(), $context->branchId());
        Gate::authorize('viewAny', CoachSchedule::class);

        return $gym;
    }

    private function lockedSchedule(CoachSchedule $bound): CoachSchedule
    {
        CoachCalendarAccess::coaches(auth()->user())->lockForUpdate()->findOrFail($bound->coach_id);

        return CoachSchedule::lockForUpdate()->findOrFail($bound->id);
    }

    public function update(CoachSchedule $bound, array $data): CoachSchedule
    {
        return DB::transaction(function () use ($bound, $data) {
            $gym = $this->lockedGym();
            $schedule = $this->lockedSchedule($bound);
            Gate::authorize('update', $schedule);
            $branch = Branch::lockForUpdate()->findOrFail($data['branch_id']);
            Gate::authorize('create', [CoachSchedule::class, $schedule->coach_id, $branch->id]);
            app(PlanLimits::class)->ensureBranchWritable($gym, $schedule->branch_id);
            app(PlanLimits::class)->ensureBranchWritable($gym, $branch->id);
            $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], $gym->timezone)->utc();
            $end = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $gym->timezone)->utc();
            $content = ['branch_id' => $branch->id, 'title' => trim($data['title']), 'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s'), 'notes' => $data['notes'] ?? null];
            $current = [...$schedule->only(['branch_id', 'title', 'notes']), 'starts_at' => $schedule->starts_at->format('Y-m-d H:i:s'), 'ends_at' => $schedule->ends_at->format('Y-m-d H:i:s')];
            if ($schedule->kind === 'appointment') {
                CoachCalendarAccess::coaches(auth()->user())->lockForUpdate()->findOrFail($data['coach_id']);
                $content = [...$content, ...CoachBookingAvailability::content($data, $start, $end)];
                $current = [...$current, ...$schedule->only(['member_purchase_id', 'coach_id', 'member_id', 'customer_name', 'service', 'location'])];
            }
            $same = $content == $current;
            $revision = (int) $data['revision'];
            if ($schedule->revision !== $revision) {
                // Exact retry of the last saved content is a no-op, never an overwrite.
                if ($schedule->revision === $revision + 1 && $same) {
                    return $schedule;
                }
                throw ValidationException::withMessages(['revision' => 'This schedule changed. Close the form and refresh the calendar before editing again.']);
            }
            if ($same) {
                return $schedule;
            }
            if (CoachSchedule::withoutGlobalScope('calendar-access')->where('coach_id', $content['coach_id'] ?? $schedule->coach_id)->whereKeyNot($schedule->id)->where('is_cancelled', false)->overlapping($start, $end)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['starts_at' => 'This coach already has a schedule during those times. Choose another time.']);
            }
            $before = $schedule->only(['branch_id', 'starts_at', 'ends_at', 'revision']);
            $changedFields = array_keys(array_filter($content, fn ($value, $key) => $value !== $current[$key], ARRAY_FILTER_USE_BOTH));
            $schedule->forceFill([...$content, 'revision' => $revision + 1])->save();
            Audit::log('coach-schedule.updated', $schedule, ['before' => $before, 'after' => [...$schedule->only(['branch_id', 'starts_at', 'ends_at', 'revision']), 'changed_fields' => $changedFields]]);

            return $schedule;
        });
    }

    public function cancel(CoachSchedule $bound, array $data): CoachSchedule
    {
        return DB::transaction(function () use ($bound, $data) {
            $gym = $this->lockedGym();
            $schedule = $this->lockedSchedule($bound);
            Gate::authorize('cancel', $schedule);
            app(PlanLimits::class)->ensureBranchWritable($gym, $schedule->branch_id);
            if ($schedule->is_cancelled) {
                return $schedule;
            }
            if ($schedule->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'This schedule changed. Close the confirmation and refresh the calendar before cancelling.']);
            }
            $before = $schedule->only(['is_cancelled', 'revision']);
            $schedule->forceFill(['is_cancelled' => true, 'cancelled_at' => now()->utc(), 'cancelled_by' => auth()->id(), 'revision' => $schedule->revision + 1])->save();
            Audit::log('coach-schedule.cancelled', $schedule, ['before' => $before, 'after' => $schedule->only(['is_cancelled', 'revision', 'cancelled_at'])]);

            return $schedule;
        });
    }

    public function status(CoachSchedule $bound, array $data): CoachSchedule
    {
        return DB::transaction(function () use ($bound, $data) {
            $gym = $this->lockedGym();
            $schedule = CoachSchedule::lockForUpdate()->findOrFail($bound->id);
            abort_unless($schedule->kind === 'appointment', 404);
            Gate::authorize('cancel', $schedule);
            app(PlanLimits::class)->ensureBranchWritable($gym, $schedule->branch_id);
            if ($schedule->status === $data['status']) {
                return $schedule;
            }
            if ($schedule->status !== 'scheduled' || $schedule->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'This appointment changed. Refresh before updating its status.']);
            }
            if ($data['status'] !== 'cancelled' && $schedule->ends_at->isFuture()) {
                throw ValidationException::withMessages(['status' => 'Wait until the appointment has ended before marking it completed or no-show.']);
            }
            $before = $schedule->only(['status', 'revision']);
            if ($data['status'] === 'completed') {
                TrainingPackage::redeem($schedule);
            }
            $schedule->forceFill(['status' => $data['status'], 'status_changed_at' => now()->utc(), 'status_changed_by' => auth()->id(), 'is_cancelled' => $data['status'] === 'cancelled', 'cancelled_at' => $data['status'] === 'cancelled' ? now()->utc() : null, 'cancelled_by' => $data['status'] === 'cancelled' ? auth()->id() : null, 'revision' => $schedule->revision + 1])->save();
            Audit::log('appointment.status-changed', $schedule, ['before' => $before, 'after' => $schedule->only(['status', 'revision'])]);

            return $schedule;
        });
    }
}
