<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Support\Audit;
use App\Support\CoachBookingAvailability;
use App\Support\CoachCalendarAccess;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateCoachSchedule
{
    public function handle(array $data): CoachSchedule
    {
        return DB::transaction(function () use ($data) {
            $context = app(CurrentGym::class);
            $gym = Gym::whereKey($context->id())->lockForUpdate()->firstOrFail();
            $gym->setRelation('subscription', $gym->subscription()->lockForUpdate()->firstOrFail());
            $context->set($gym, $context->role(), $context->branchId());
            Gate::authorize('viewAny', CoachSchedule::class);
            $coach = CoachCalendarAccess::coaches(auth()->user())->lockForUpdate()->findOrFail($data['coach_id']);
            $branch = Branch::lockForUpdate()->findOrFail($data['branch_id']);
            Gate::authorize('create', [CoachSchedule::class, $coach->id, $branch->id]);
            app(PlanLimits::class)->ensureBranchWritable($gym, $branch->id);
            $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], $gym->timezone)->utc();
            $end = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $gym->timezone)->utc();
            $content = ['coach_id' => $coach->id, 'branch_id' => $branch->id, 'title' => trim($data['title']), 'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s'), 'notes' => $data['notes'] ?? null];
            $content['kind'] = $data['kind'] ?? 'block';
            if ($content['kind'] === 'appointment') {
                $content = [...$content, ...CoachBookingAvailability::content($data, $start, $end)];
            }
            $hash = hash('sha256', json_encode(['actor' => auth()->id(), ...$content], JSON_THROW_ON_ERROR));
            $key = strtolower($data['request_key']);
            $existing = CoachSchedule::withoutGlobalScope('calendar-access')->where('request_key', $key)->first();
            if ($existing) {
                Gate::authorize('view', $existing);
                if ($existing->creation_hash !== $hash) {
                    throw ValidationException::withMessages(['request_key' => 'This submission was already used for a different schedule.']);
                }

                return $existing;
            }
            // Check all branches for this coach; a manager must not bypass conflicts hidden by their scope.
            if (CoachSchedule::withoutGlobalScope('calendar-access')->where('coach_id', $coach->id)->where('is_cancelled', false)->overlapping($start, $end)->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages(['starts_at' => 'This coach already has a schedule during those times. Choose another time.']);
            }
            $schedule = (new CoachSchedule)->forceFill([...$content, 'request_key' => $key, 'creation_hash' => $hash, 'created_by' => auth()->id(), 'is_cancelled' => false]);
            $schedule->save();
            Audit::log('coach-schedule.created', $schedule, ['after' => $schedule->only(['coach_id', 'branch_id', 'starts_at', 'ends_at'])]);

            return $schedule;
        });
    }
}
