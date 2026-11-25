<?php

namespace App\Http\Middleware;

use App\Enums\MemberQrPurpose;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Support\LegalDocuments;
use App\Tenancy\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EnsurePortalMember
{
    public function handle(Request $request, Closure $next)
    {
        $guard = Auth::guard('member');
        $gym = app(CurrentGym::class)->get();
        $member = $guard->id() ? Member::whereKey($guard->id())->where('is_active', true)->first() : null;
        $proof = $request->session()->get('portal_verified', []);
        $valid = $member && $member->portal_password && ($proof['gym_id'] ?? null) === $gym->id
            && ($proof['expires_at'] ?? 0) > now()->timestamp
            && hash_equals(hash('sha256', $member->portal_password), $proof['password_hash'] ?? '');
        if ($valid && isset($proof['qr_id'])) {
            $valid = MemberQrToken::usable(MemberQrPurpose::Portal)->whereKey($proof['qr_id'])->where('member_id', $member->id)->exists();
        }
        if (! $valid) {
            $guard->logout();
            $request->session()->forget(['portal_verified', 'portal_pending']);
            Inertia::clearHistory();

            return redirect()->route('portal.login', ['gym' => $gym->slug]);
        }
        $request->attributes->set('portalMember', $member);

        if (! $request->routeIs('portal.agreement', 'portal.accept-agreement') && (! $member->terms_accepted_at || $member->terms_version !== LegalDocuments::bundle()['version'])) {
            return redirect()->route('portal.agreement', ['gym' => $gym->slug]);
        }

        return $next($request);
    }
}
