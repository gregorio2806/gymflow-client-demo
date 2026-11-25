<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AccountAgreementRequest;
use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\Member;
use App\Support\LegalDocuments;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountAgreementController extends Controller
{
    public function show()
    {
        $gym = app(CurrentGym::class)->get();

        return Inertia::render('portal/Agreement', ['gymName' => $gym->name, 'gymSlug' => $gym->slug, 'legal' => LegalDocuments::bundle()]);
    }

    public function store(AccountAgreementRequest $request)
    {
        $data = LegalDocuments::validate($request->validated());
        DB::transaction(function () use ($request, $data) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $member = Member::whereKey($request->attributes->get('portalMember')->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($request->session()->get('portal_verified.password_hash', ''), hash('sha256', $member->portal_password ?? '')), 403);
            if ($member->terms_version === $data['policy_version'] && $member->terms_accepted_at) {
                return;
            }
            $member->forceFill(['terms_version' => $data['policy_version'], 'terms_accepted_at' => now()])->save();
            $log = new AuditLog(['user_id' => null, 'action' => 'member.account-agreement.accepted', 'changes' => ['after' => ['version' => $data['policy_version']]]]);
            $log->subject()->associate($member);
            $log->save();
        });
        Inertia::clearHistory();

        return to_route('portal.home', ['gym' => app(CurrentGym::class)->get()->slug]);
    }
}
