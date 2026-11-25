<?php

namespace App\Console\Commands;

use App\Enums\GymFeature;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

#[Signature('demo:purge')]
#[Description('Delete expired "Try demo" gyms and their throwaway accounts')]
class PurgeExpiredDemos extends Command
{
    public function handle(): int
    {
        $purged = 0;

        Gym::whereNotNull('demo_expires_at')
            ->where('demo_expires_at', '<=', now())
            ->chunkById(100, function ($gyms) use (&$purged) {
                foreach ($gyms as $gym) {
                    $userIds = $gym->users()->where('is_demo', true)->pluck('users.id');

                    Feature::for($gym)->forget(array_map(fn (GymFeature $f) => $f->value, GymFeature::cases()));
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete(); // log them out
                    $gym->delete(); // cascades: memberships, audit logs, invitations
                    User::whereIn('id', $userIds)->delete();

                    $purged++;
                }
            });

        // Demo accounts left without a gym (e.g. a failed start).
        User::where('is_demo', true)->where('created_at', '<=', now()->subHours(24))->delete();

        $this->info("Purged {$purged} expired demo gym(s).");

        return self::SUCCESS;
    }
}
