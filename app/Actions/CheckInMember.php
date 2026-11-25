<?php

namespace App\Actions;

use App\Enums\MemberQrPurpose;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Support\Audit;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CheckInMember
{
    /** @return array{record: AttendanceRecord, duplicate: bool} */
    public function handle(array $data): array
    {
        Gate::authorize('create', AttendanceRecord::class);

        $context = app(CurrentGym::class);
        $branch = Branch::findOrFail($data['branch_id']);
        $token = null;
        if (! empty($data['token'])) {
            // Database cache counters must survive a rejected check-in's transaction rollback.
            $failureKey = 'attendance-qr-failures:'.app(CurrentGym::class)->id().':'.request()->ip();
            abort_if(RateLimiter::tooManyAttempts($failureKey, 10), 429, __('Too many invalid QR attempts. Wait a minute or use member search.'));
            $token = $context->runAs($context->get(), fn () => MemberQrToken::usable(MemberQrPurpose::Attendance)->where('token_hash', hash('sha256', substr($data['token'], strlen('gym-attendance:'))))->first());
            if (! $token) {
                RateLimiter::hit($failureKey, 60);
                Log::notice('Attendance QR rejected', ['gym_id' => app(CurrentGym::class)->id(), 'user_id' => auth()->id(), 'ip' => request()->ip()]);
                throw ValidationException::withMessages(['token' => __('This QR is invalid, revoked, or expired. Use the current card or search for the member.')]);
            }
        }

        // Memberships are gym-wide; retain tenant isolation after authorizing the visited branch.
        return $context->runAs($context->get(), fn () => DB::transaction(function () use ($data, $branch, $token) {
            $member = Member::lockForUpdate()->findOrFail($token?->member_id ?? $data['member_id']);
            $at = CarbonImmutable::now();
            if ($token && ! MemberQrToken::usable(MemberQrPurpose::Attendance, $at)->whereKey($token->id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['token' => __('This QR no longer works. Use the current card.')]);
            }
            if (! $member->is_active || ! $member->subscriptions()->where('status', 'active')->where('starts_at', '<=', $at)->where('ends_at', '>', $at)->get()->contains(fn ($s) => empty($s->pricing_snapshot['branch_ids']) || in_array($branch->id, $s->pricing_snapshot['branch_ids']))) {
                throw ValidationException::withMessages(['member_id' => __('Check-in requires an active member profile and a currently valid, unsuspended membership.')]);
            }
            $minutes = max(0, (int) config('attendance.duplicate_window_minutes', 5));
            if ($minutes > 0) {
                $existing = AttendanceRecord::where('member_id', $member->id)->where('branch_id', $branch->id)
                    ->where('checked_in_at', '>', $at->subMinutes($minutes))->where('checked_in_at', '<=', $at)
                    ->orderByDesc('checked_in_at')->orderByDesc('id')->first();
                if ($existing) {
                    return ['record' => $existing, 'duplicate' => true];
                }
            }
            $record = new AttendanceRecord;
            $record->forceFill(['member_id' => $member->id, 'branch_id' => $branch->id, 'recorded_by' => auth()->id(), 'checked_in_at' => $at, 'source' => $token ? 'qr' : 'manual'])->save();
            Audit::log('attendance.checked-in', $record, ['after' => $record->only(['member_id', 'branch_id', 'checked_in_at', 'source'])]);

            return ['record' => $record, 'duplicate' => false];
        }));
    }
}
