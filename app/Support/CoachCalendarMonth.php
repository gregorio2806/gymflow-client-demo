<?php

namespace App\Support;

use App\Enums\AppointmentService;
use App\Models\CoachSchedule;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;

class CoachCalendarMonth
{
    /** One aggregate query and at most 31 cells, without loading schedule records. */
    public function build(int $coachId, CarbonImmutable $month, string $selected, string $today, ?int $length = null): array
    {
        $end = $length ? $month->addDays($length) : $month->addMonth();
        $query = CoachSchedule::where('coach_id', $coachId)->where('is_cancelled', false)->overlapping($month->utc(), $end->utc());
        $query = app(CurrentGym::class)->filterView($query);
        $days = [];
        for ($at = $month; $at->lessThan($end); $at = $at->addDay()) {
            $number = $at->day;
            $query->selectRaw("COALESCE(SUM(CASE WHEN starts_at < ? AND ends_at > ? THEN 1 ELSE 0 END), 0) AS day_{$number}", [$at->addDay()->utc(), $at->utc()]);
            $date = $at->toDateString();
            $services = [];
            foreach (AppointmentService::cases() as $i => $service) {
                $query->selectRaw("COALESCE(SUM(CASE WHEN starts_at < ? AND ends_at > ? AND service = ? THEN 1 ELSE 0 END), 0) AS service_{$number}_{$i}", [$at->addDay()->utc(), $at->utc(), $service->value]);
                $services[] = ['value' => $service->value, 'label' => $service->label(), 'count' => 0];
            }
            $days[] = ['date' => $date, 'number' => $number, 'count' => 0, 'today' => $date === $today, 'selected' => $date === $selected, 'services' => $services];
        }
        $totals = $query->toBase()->first();
        foreach ($days as &$day) {
            $day['count'] = (int) $totals->{'day_'.$day['number']};
            foreach ($day['services'] as $i => &$service) {
                $service['count'] = (int) $totals->{'service_'.$day['number'].'_'.$i};
            }
            unset($service);
        }

        return $days;
    }
}
