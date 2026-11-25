<?php

namespace App\Http\Controllers\Portal;

use App\Enums\MemberQrPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LoginRequest;
use App\Models\MemberQrToken;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function access()
    {
        return Inertia::render('portal/Access', ['gymName' => app(CurrentGym::class)->get()->name, 'gymSlug' => app(CurrentGym::class)->get()->slug]);
    }

    public function resolve(Request $request)
    {
        $validator = Validator::make($request->only('token'), ['token' => ['required', 'string', 'regex:/\A[a-zA-Z0-9]{64}\z/']]);
        $token = $validator->fails() ? null : MemberQrToken::usable(MemberQrPurpose::Portal)->where('token_hash', hash('sha256', $request->input('token')))->whereHas('member', fn ($query) => $query->where('is_active', true)->whereNotNull('portal_password'))->first();
        if (! $token) {
            $request->session()->forget('portal_pending');

            return response()->json(['errors' => ['token' => ['This portal link is invalid or unavailable. Ask the gym for a new link.']]], 422);
        }
        Auth::guard('member')->logout();
        $request->session()->regenerate();
        $request->session()->forget('portal_verified');
        $request->session()->put('portal_pending', ['gym_id' => app(CurrentGym::class)->id(), 'qr_id' => $token->id, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return response()->json(['verified' => false, 'redirect' => route('portal.login', ['gym' => app(CurrentGym::class)->get()->slug])]);
    }

    public function create()
    {
        return Inertia::render('portal/Login', ['gymName' => app(CurrentGym::class)->get()->name, 'gymSlug' => app(CurrentGym::class)->get()->slug]);
    }

    public function store(LoginRequest $request)
    {
        $guard = Auth::guard('member');
        if (! $guard->attempt(['portal_login' => $request->validated('login'), 'password' => $request->validated('password'), 'is_active' => true])) {
            throw ValidationException::withMessages(['login' => 'The member login or password is incorrect.']);
        }
        $member = $guard->user();
        $gym = app(CurrentGym::class)->get();
        $pending = $request->session()->get('portal_pending');
        $qrId = null;
        if ($pending) {
            $qrId = $pending['qr_id'] ?? null;
            $valid = ($pending['gym_id'] ?? null) === $gym->id && ($pending['expires_at'] ?? 0) > now()->timestamp
                && MemberQrToken::usable(MemberQrPurpose::Portal)->whereKey($qrId)->where('member_id', $member->id)->exists();
            if (! $valid) {
                $guard->logout();

                throw ValidationException::withMessages(['login' => 'The login could not verify this portal link. Ask the gym for help.']);
            }
        }
        $request->session()->regenerate();
        $request->session()->forget('portal_pending');
        $proof = ['gym_id' => $gym->id, 'password_hash' => hash('sha256', $member->portal_password), 'expires_at' => now()->addMinutes(30)->timestamp];
        if ($qrId) {
            $proof['qr_id'] = $qrId;
        }
        $request->session()->put('portal_verified', $proof);
        Inertia::clearHistory();

        return redirect()->route('portal.home', ['gym' => $gym->slug]);
    }

    public function destroy(Request $request)
    {
        Auth::guard('member')->logout();
        $request->session()->forget(['portal_verified', 'portal_pending']);
        $request->session()->regenerateToken();
        $request->session()->regenerate();
        Inertia::clearHistory();

        return redirect()->route('portal.login', ['gym' => app(CurrentGym::class)->get()->slug]);
    }
}
