<?php

namespace App\Actions;

use App\Enums\MemberQrPurpose;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageAttendanceQr
{
    public function handle(Member $member, string $operation, bool $authorize = true): ?string
    {
        return DB::transaction(function () use ($member, $operation, $authorize) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $member = Member::lockForUpdate()->findOrFail($member->id);
            if ($authorize) {
                Gate::authorize('update', $member);
            }
            app(PlanLimits::class)->ensureBranchWritable($gym, $member->home_branch_id, 'operation');
            $existing = $member->qrTokens()->where('purpose', MemberQrPurpose::Attendance)->whereNull('revoked_at')->lockForUpdate()->get();
            if ($operation === 'view') {
                $token = $existing->first();
                if (! $token?->encrypted_payload) {
                    throw ValidationException::withMessages(['operation' => __('This QR was created before recovery was added. Reissue it to create a recoverable card.')]);
                }

                try {
                    return Crypt::decryptString($token->encrypted_payload);
                } catch (DecryptException) {
                    throw ValidationException::withMessages(['operation' => __('This QR cannot be recovered. Reissue it to create a new card.')]);
                }
            }
            if ($operation === 'generate' && $existing->isNotEmpty()) {
                throw ValidationException::withMessages(['operation' => __('This member already has a QR. Reissue it to replace the previous card.')]);
            }
            foreach ($existing as $token) {
                $token->revoked_at = now();
                $token->encrypted_payload = null;
                $token->save();
                Audit::log('attendance-qr.revoked', $token);
            }
            if ($operation === 'revoke') {
                return null;
            }
            $raw = Str::random(64);
            $token = new MemberQrToken;
            $token->forceFill(['member_id' => $member->id, 'purpose' => MemberQrPurpose::Attendance, 'token_hash' => hash('sha256', $raw), 'encrypted_payload' => Crypt::encryptString('gym-attendance:'.$raw)])->save();
            Audit::log($operation === 'reissue' ? 'attendance-qr.reissued' : 'attendance-qr.generated', $token, ['after' => ['member_id' => $member->id, 'purpose' => 'attendance']]);

            return 'gym-attendance:'.$raw;
        });
    }
}
