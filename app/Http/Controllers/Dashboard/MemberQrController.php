<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\ManageAttendanceQr;
use App\Enums\MemberQrPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ManageAttendanceQrRequest;
use App\Models\Branch;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberQrController extends Controller
{
    public function show(Member $member): Response
    {
        Gate::authorize('update', $member);

        return Inertia::render('dashboard/members/Qr', [
            'member' => $member->only(['id', 'name']),
            'hasQr' => $member->qrTokens()->where('purpose', MemberQrPurpose::Attendance)->whereNull('revoked_at')->exists(),
            'canManage' => Branch::whereKey($member->home_branch_id)->where('is_active', true)->exists(),
        ]);
    }

    public function store(ManageAttendanceQrRequest $request, Member $member, ManageAttendanceQr $action): JsonResponse
    {
        $payload = $action->handle($member, $request->validated('operation'));

        // Return once to the requesting browser; never put QR secrets in sessions or Inertia history.
        return response()->json(['payload' => $payload, 'hasQr' => $payload !== null])->header('Cache-Control', 'no-store, private');
    }
}
