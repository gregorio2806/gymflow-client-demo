<?php

namespace App\Http\Controllers\Portal;

use App\Actions\ManageCalorieProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SaveCalorieProfileRequest;
use App\Models\MemberCalorieProfile;
use App\Support\CalorieEstimate;
use App\Tenancy\CurrentGym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class CalorieProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $member = $request->attributes->get('portalMember');
        $gym = app(CurrentGym::class)->get();
        $profile = MemberCalorieProfile::where('member_id', $member->id)->first();
        $enabled = Feature::for($gym)->active('calorie-helper');
        abort_unless($enabled || $profile, 404);

        return Inertia::render('portal/CalorieHelper', ['gymName' => $gym->name, 'gymSlug' => $gym->slug, 'memberName' => $member->name, 'workoutsEnabled' => Feature::for($gym)->active('workouts'), 'calorieHelperAvailable' => true, 'enabled' => $enabled, 'hasProfile' => (bool) $profile, 'profile' => $enabled ? $profile?->details : null, 'estimate' => $enabled && $profile ? CalorieEstimate::calculate($profile->details) : null, 'consentVersion' => CalorieEstimate::CONSENT_VERSION]);
    }

    public function store(SaveCalorieProfileRequest $request, ManageCalorieProfile $action): JsonResponse
    {
        $action->handle($request->attributes->get('portalMember'), $request->validated());

        $profile = MemberCalorieProfile::where('member_id', $request->attributes->get('portalMember')->id)->firstOrFail();

        return response()->json(['saved' => true, 'estimate' => CalorieEstimate::calculate($profile->details)]);
    }

    public function destroy(Request $request, ManageCalorieProfile $action): JsonResponse
    {
        $action->handle($request->attributes->get('portalMember'), null);
        Inertia::clearHistory();

        return response()->json(['deleted' => true]);
    }
}
