<?php

namespace App\Console\Commands;

use App\Enums\MemberSubscriptionStatus;
use App\Models\Gym;
use App\Models\MemberSubscription;
use App\Support\Audit;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('memberships:expire')]
#[Description('Mark ended active and suspended member subscriptions as expired')]
class ExpireMemberSubscriptions extends Command
{
    public function handle(): int
    {
        $cutoff = CarbonImmutable::now('UTC');
        $statuses = [MemberSubscriptionStatus::Active->value, MemberSubscriptionStatus::Suspended->value];
        $expired = 0;

        Gym::chunkById(100, function ($gyms) use ($cutoff, $statuses, &$expired) {
            foreach ($gyms as $gym) {
                app(CurrentGym::class)->runAs($gym, function () use ($gym, $cutoff, $statuses, &$expired) {
                    MemberSubscription::whereIn('status', $statuses)->where('ends_at', '<=', $cutoff)
                        ->select('id')->chunkById(100, function ($subscriptions) use ($gym, $cutoff, $statuses, &$expired) {
                            $expired += DB::transaction(function () use ($gym, $cutoff, $statuses, $subscriptions) {
                                // Match membership-write lock order, then recheck eligibility under lock.
                                if (! Gym::whereKey($gym->id)->lockForUpdate()->first()) {
                                    return 0;
                                }
                                $periods = MemberSubscription::whereKey($subscriptions->modelKeys())
                                    ->whereIn('status', $statuses)->where('ends_at', '<=', $cutoff)
                                    ->orderBy('id')->lockForUpdate()->get();
                                foreach ($periods as $period) {
                                    $period->status = MemberSubscriptionStatus::Expired;
                                    $period->save();
                                    Audit::log('membership.expired', $period);
                                }

                                return $periods->count();
                            }, 3);
                        });
                });
            }
        });

        $this->info("Expired {$expired} member subscription(s).");

        return self::SUCCESS;
    }
}
