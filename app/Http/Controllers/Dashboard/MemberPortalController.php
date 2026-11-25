<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\ManageMemberPortal;
use App\Enums\MemberQrPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ManageMemberPortalRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MemberPortalController extends Controller
{
    public function show(Member $member)
    {
        Gate::authorize('update', $member);

        return Inertia::render('dashboard/members/Portal', [
            'member' => $member->only(['id', 'name']), 'login' => $member->portal_login,
            'hasLogin' => $member->portal_password !== null,
            'hasStatusCode' => $member->status_code_hash !== null,
            'hasQr' => $member->qrTokens()->where('purpose', MemberQrPurpose::Portal)->whereNull('revoked_at')->exists(),
            'canManage' => Branch::whereKey($member->home_branch_id)->where('is_active', true)->exists(),
            'loginUrl' => route('portal.login', ['gym' => app(CurrentGym::class)->get()->slug]),
        ]);
    }

    public function store(ManageMemberPortalRequest $request, Member $member, ManageMemberPortal $action)
    {
        return response()->json($action->handle($member, $request->validated('operation'), $request->validated('login')))->header('Cache-Control', 'private, no-store');
    }
}
