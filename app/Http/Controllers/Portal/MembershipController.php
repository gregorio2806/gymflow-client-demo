<?php

namespace App\Http\Controllers\Portal;

use App\Enums\MemberSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\MemberCalorieProfile;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Pennant\Feature;

class MembershipController extends Controller
{
    public function __invoke(Request $request)
    {
        $member = $request->attributes->get('portalMember');
        $gym = app(CurrentGym::class)->get();
        $at = now()->utc();
        $period = $member->subscriptions()->where('status', '!=', MemberSubscriptionStatus::Cancelled)->where('starts_at', '<=', $at)->where('ends_at', '>', $at)->orderByDesc('starts_at')->orderByDesc('id')->first()
            ?? $member->subscriptions()->where('status', '!=', MemberSubscriptionStatus::Cancelled)->where('starts_at', '>', $at)->orderBy('starts_at')->orderBy('id')->first()
            ?? $member->subscriptions()->orderByDesc('starts_at')->orderByDesc('id')->first();

        return Inertia::render('portal/Membership', [
            'gymName' => $gym->name, 'gymSlug' => $gym->slug,
            'memberName' => $member->name,
            'workoutsEnabled' => Feature::for($gym)->active('workouts'),
            'calorieHelperAvailable' => Feature::for($gym)->active('calorie-helper') || MemberCalorieProfile::where('member_id', $member->id)->exists(),
            'membership' => $period ? [
                'planName' => $period->plan_name, 'status' => $period->displayStatus($at, $gym->timezone),
                'startsOn' => $period->starts_at->setTimezone($gym->timezone)->format('M j, Y'),
                'validThrough' => $period->ends_at->subSecond()->setTimezone($gym->timezone)->format('M j, Y'),
            ] : null,
            'renewalInstructions' => 'Visit the gym front desk to renew your membership. Ask the staff to confirm your plan and new end date.',
        ]);
    }
}
