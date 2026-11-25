<?php

namespace App\Actions;

use App\Enums\MemberQrPurpose;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageMemberPortal
{
    public function handle(Member $member, string $operation, ?string $login): array
    {
        $login = $login === null ? null : strtolower(trim($login));

        return DB::transaction(function () use ($member, $operation, $login) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $member = Member::lockForUpdate()->findOrFail($member->id);
            Gate::authorize('update', $member);
            app(PlanLimits::class)->ensureBranchWritable($gym, $member->home_branch_id, 'operation');
            $credentials = null;
            $payload = null;
            $statusCode = null;
            if (in_array($operation, ['create-login', 'reset-password', 'disable-login'], true)) {
                if ($operation === 'create-login') {
                    if ($member->portal_password || Member::where('portal_login', $login)->whereKeyNot($member->id)->exists()) {
                        throw ValidationException::withMessages(['login' => 'This member already has a login, or that login is taken in this gym.']);
                    }
                    $member->portal_login = $login;
                } elseif (! $member->portal_login || ! $member->portal_password) {
                    throw ValidationException::withMessages(['operation' => 'Create a member login first.']);
                }
                $password = $operation === 'disable-login' ? null : Str::password(20);
                $member->portal_password = $password ? Hash::make($password) : null;
                if ($operation === 'disable-login') {
                    $member->portal_login = null;
                }
                $member->save();
                Audit::log('member-portal.'.$operation, $member, ['after' => ['member_id' => $member->id, 'operation' => $operation]]);
                $credentials = $password ? ['login' => $member->portal_login, 'password' => $password] : null;
            } elseif (in_array($operation, ['issue-status-code', 'revoke-status-code'], true)) {
                $raw = $operation === 'revoke-status-code' ? null : strtoupper(bin2hex(random_bytes(10)));
                $member->status_code_hash = $raw ? hash('sha256', $raw) : null;
                $member->save();
                $statusCode = $raw ? implode('-', str_split($raw, 4)) : null;
                Audit::log('member-portal.'.$operation, $member, ['after' => ['member_id' => $member->id, 'operation' => $operation]]);
            } else {
                if (! $member->portal_password && $operation !== 'revoke-qr') {
                    throw ValidationException::withMessages(['operation' => 'Create a member login before issuing a portal QR.']);
                }
                $existing = $member->qrTokens()->where('purpose', MemberQrPurpose::Portal)->whereNull('revoked_at')->lockForUpdate()->get();
                if ($operation === 'generate-qr' && $existing->isNotEmpty()) {
                    throw ValidationException::withMessages(['operation' => 'A portal QR already exists. Reissue it to replace the old link.']);
                }
                foreach ($existing as $token) {
                    $token->revoked_at = now()->utc();
                    $token->save();
                    Audit::log('member-portal.qr-revoked', $token, ['after' => ['member_id' => $member->id]]);
                }
                if ($operation !== 'revoke-qr') {
                    $raw = Str::random(64);
                    $token = new MemberQrToken;
                    $token->forceFill(['member_id' => $member->id, 'purpose' => MemberQrPurpose::Portal, 'token_hash' => hash('sha256', $raw)])->save();
                    Audit::log('member-portal.'.$operation, $token, ['after' => ['member_id' => $member->id, 'purpose' => 'portal']]);
                    // Fragment secrets never reach request URLs, referrers, or server access logs.
                    $payload = route('portal.access', ['gym' => $gym->slug]).'#'.$raw;
                }
            }

            return ['statusCode' => $statusCode, 'hasStatusCode' => $member->status_code_hash !== null, 'credentials' => $credentials, 'payload' => $payload, 'hasLogin' => $member->portal_password !== null, 'hasQr' => $member->qrTokens()->where('purpose', MemberQrPurpose::Portal)->whereNull('revoked_at')->exists()];
        });
    }
}
