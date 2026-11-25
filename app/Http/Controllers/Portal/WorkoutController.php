<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MemberCalorieProfile;
use App\Models\MemberRoutineAssignment;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class WorkoutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate(['day' => ['nullable', 'integer', 'min:1', 'max:7'], 'page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $member = $request->attributes->get('portalMember');
        $gym = app(CurrentGym::class)->get();
        // Never accept a member or routine identifier from the visitor.
        $assignment = MemberRoutineAssignment::where('member_id', $member->id)->where('branch_id', $member->home_branch_id)->whereNull('ended_at')->orderByDesc('id')->first();
        $dayNumber = (int) ($filters['day'] ?? 1);
        $pageNumber = (int) ($filters['page'] ?? 1);
        $routine = null;
        $exercises = null;
        if ($assignment) {
            $snapshot = $assignment->snapshot;
            abort_unless(isset($snapshot['days'][$dayNumber - 1]), 404);
            $day = $snapshot['days'][$dayNumber - 1];
            $items = array_map(fn ($exercise) => ['name' => $exercise['name'], 'sets' => $exercise['sets'], 'reps' => $exercise['reps'], 'notes' => $exercise['notes'] ?? ''], $day['exercises']);
            $exercises = new LengthAwarePaginator(array_slice($items, ($pageNumber - 1) * 5, 5), count($items), 5, $pageNumber, ['path' => $request->url(), 'query' => ['day' => $dayNumber]]);
            $routine = ['name' => $snapshot['name'], 'notes' => $snapshot['notes'] ?? '', 'version' => $assignment->routine_revision, 'assignedAt' => $assignment->assigned_at->setTimezone($gym->timezone)->format('M j, Y g:i A'), 'days' => array_map(fn ($item) => $item['name'], $snapshot['days']), 'selectedDay' => $dayNumber];
        }

        return Inertia::render('portal/Workouts', ['gymName' => $gym->name, 'gymSlug' => $gym->slug, 'memberName' => $member->name, 'workoutsEnabled' => true, 'calorieHelperAvailable' => Feature::for($gym)->active('calorie-helper') || MemberCalorieProfile::where('member_id', $member->id)->exists(), 'routine' => $routine, 'exercises' => $exercises]);
    }
}
