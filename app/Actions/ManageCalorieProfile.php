<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberCalorieProfile;
use App\Support\CalorieEstimate;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;

class ManageCalorieProfile
{
    public function handle(Member $verified, ?array $data): void
    {
        DB::transaction(function () use ($verified, $data) {
            Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $member = Member::whereKey($verified->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless($member->portal_password && hash_equals(hash('sha256', $member->portal_password), request()->session()->get('portal_verified.password_hash', '')), 403);
            $profile = MemberCalorieProfile::where('member_id', $member->id)->lockForUpdate()->first();
            if ($data === null) {
                if ($profile) {
                    $profile->delete();
                    $this->audit('calorie-profile.deleted', $member, ['deleted' => true]);
                }

                return;
            }
            $details = ['age' => (int) $data['age'], 'height_cm' => (float) $data['height_cm'], 'weight_kg' => (float) $data['weight_kg'], 'equation' => $data['equation'], 'activity' => $data['activity'], 'goal' => $data['goal']];
            if ($profile && $profile->details == $details && $profile->consent_version === CalorieEstimate::CONSENT_VERSION) {
                return;
            }
            $profile ??= (new MemberCalorieProfile)->forceFill(['member_id' => $member->id]);
            $profile->forceFill(['details' => $details, 'consent_version' => CalorieEstimate::CONSENT_VERSION, 'consented_at' => now()->utc()])->save();
            $this->audit('calorie-profile.saved', $member, ['consent_version' => CalorieEstimate::CONSENT_VERSION]);
        });
    }

    private function audit(string $event, Member $member, array $changes): void
    {
        $audit = new AuditLog(['user_id' => null, 'action' => $event, 'changes' => ['after' => $changes]]);
        $audit->subject()->associate($member);
        $audit->save();
    }
}
