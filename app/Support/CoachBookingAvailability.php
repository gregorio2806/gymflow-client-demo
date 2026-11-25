<?php

namespace App\Support;

use App\Enums\AppointmentService;
use App\Enums\GymRole;
use App\Models\CoachSchedule;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CoachBookingAvailability
{
    public static function membership(int $coachId)
    {
        return app(CurrentGym::class)->get()->users()->whereKey($coachId)->wherePivot('role', GymRole::Coach->value)->wherePivot('is_active', true)->firstOrFail()->pivot;
    }

    public static function hours(int $coachId): array
    {
        return self::membership($coachId)->availability ?? array_fill(0, 7, ['enabled' => false, 'start' => '09:00', 'end' => '18:00']);
    }

    public static function assigned(int $coachId, int $branchId): bool
    {
        return in_array($branchId, TeamBranches::ids(self::membership($coachId)), true);
    }

    public static function ensureHours(int $coachId, int $branchId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $timezone = app(CurrentGym::class)->get()->timezone;
        $local = $start->setTimezone($timezone);
        $through = $end->setTimezone($timezone);
        $hours = self::hours($coachId)[$local->dayOfWeek];
        if (! self::assigned($coachId, $branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'Choose a branch assigned to this coach.']);
        }
        if (! $hours['enabled'] || ! $local->isSameDay($through) || $local->format('H:i') < $hours['start'] || $through->format('H:i') > $hours['end']) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a time within this coach\'s availability.']);
        }
    }

    public static function slots(int $coachId, int $branchId, string $date, int $duration, ?int $except = null): array
    {
        if (! self::assigned($coachId, $branchId)) {
            return [];
        }
        $day = CarbonImmutable::parse($date, app(CurrentGym::class)->get()->timezone)->startOfDay();
        $hours = self::hours($coachId)[$day->dayOfWeek];
        if (! $hours['enabled']) {
            return [];
        }
        $start = CarbonImmutable::parse($date.' '.$hours['start'], $day->timezone);
        $end = CarbonImmutable::parse($date.' '.$hours['end'], $day->timezone);
        $busy = CoachSchedule::withoutGlobalScope('calendar-access')->where('coach_id', $coachId)->where('is_cancelled', false)
            ->when($except, fn ($q) => $q->whereKeyNot($except))->overlapping($day->utc(), $day->addDay()->utc())->get(['starts_at', 'ends_at']);
        $slots = [];
        for ($at = $start; $at->addMinutes($duration)->lessThanOrEqualTo($end); $at = $at->addMinutes(30)) {
            $through = $at->addMinutes($duration);
            if (! $busy->contains(fn ($row) => $row->starts_at->lessThan($through->utc()) && $row->ends_at->greaterThan($at->utc()))) {
                $slots[] = ['starts_at' => $at->format('Y-m-d\TH:i'), 'ends_at' => $through->format('Y-m-d\TH:i'), 'label' => $at->format('g:i A').' – '.$through->format('g:i A')];
            }
        }

        return $slots;
    }

    public static function canEdit(User $user): bool
    {
        return in_array(CoachCalendarAccess::actor($user)?->pivot->role, [GymRole::Owner, GymRole::Staff, GymRole::Coach], true);
    }

    public static function content(array $data, CarbonImmutable $start, CarbonImmutable $end): array
    {
        abort_if(CoachCalendarAccess::actor(auth()->user())?->pivot->role === GymRole::Coach, 403);
        Gate::authorize('create', [CoachSchedule::class, (int) $data['coach_id'], (int) $data['branch_id']]);
        self::ensureHours((int) $data['coach_id'], (int) $data['branch_id'], $start, $end);
        if ($start->diffInMinutes($end) < 15 || $start->diffInMinutes($end) > 480) {
            throw ValidationException::withMessages(['ends_at' => 'Choose a duration between 15 minutes and 8 hours.']);
        }
        $member = ! empty($data['member_id']) ? Member::withoutGlobalScope('branch')->where('is_active', true)->findOrFail($data['member_id']) : null;
        if (! empty($data['member_purchase_id'])) {
            if (! $member) {
                throw ValidationException::withMessages(['member_purchase_id' => __('Select a member before choosing a package.')]);
            }
            TrainingPackage::eligible((int) $data['member_purchase_id'], $member->id, (int) $data['branch_id'], (int) $data['coach_id'], $start, $end);
        }

        return ['member_purchase_id' => ! empty($data['member_purchase_id']) ? (int) $data['member_purchase_id'] : null, 'coach_id' => (int) $data['coach_id'], 'member_id' => $member?->id, 'customer_name' => $member?->name ?? trim($data['customer_name']), 'service' => $data['service'], 'title' => AppointmentService::from($data['service'])->label(), 'location' => $data['location'] ?? null];
    }
}
