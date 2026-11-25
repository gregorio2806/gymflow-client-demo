<?php

namespace Database\Seeders;

use App\Enums\GymRole;
use App\Enums\MemberSubscriptionStatus;
use App\Enums\PaymentMethod;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\CoachSchedule;
use App\Models\Gym;
use App\Models\GymWebsite;
use App\Models\Member;
use App\Models\MemberRoutineAssignment;
use App\Models\MembershipPlan;
use App\Models\MemberSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkoutRoutine;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class LoadTestingSeeder extends Seeder
{
    public const SLUG = 'fictional-load-test';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('LoadTestingSeeder is local/testing only.');
        }
        $gym = Gym::firstOrCreate(['slug' => self::SLUG], ['name' => 'Fictional Load Test Gym', 'address' => 'Fictional development location']);
        if ($gym->wasRecentlyCreated) {
            $gym->subscription->update(['plan' => Plan::MultiBranch]);
        }
        if ($gym->plan() !== Plan::MultiBranch || $gym->name !== 'Fictional Load Test Gym') {
            throw new RuntimeException('Existing fixture changed. Refusing to overwrite it.');
        }
        app(CurrentGym::class)->runAs($gym, function () use ($gym) {
            $owner = $this->account($gym, 'owner', GymRole::Owner);
            $this->account($gym, 'staff', GymRole::Staff);
            $coach = $this->account($gym, 'coach', GymRole::Coach);
            $this->account($gym, 'manager', GymRole::BranchManager);
            $main = $gym->mainBranch();
            $second = Branch::firstOrCreate(['name' => 'Fictional second branch']);
            $plan = MembershipPlan::firstOrNew(['name' => 'Fictional monthly membership']);
            if (! $plan->exists) {
                $plan->forceFill(['duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000, 'inclusions' => 'Fictional development membership'])->save();
            }
            $today = CarbonImmutable::now($gym->timezone)->startOfDay();
            foreach (array_chunk(range(1, 1000), 100) as $chunk) {
                DB::transaction(function () use ($chunk, $gym, $owner, $main, $second, $plan, $today) {
                    $gym->newQuery()->whereKey($gym->id)->lockForUpdate()->firstOrFail();
                    $gym->subscription()->lockForUpdate()->firstOrFail();
                    foreach ($chunk as $index) {
                        $branch = $index <= 500 ? $main : $second;
                        $email = 'member'.str_pad((string) $index, 4, '0', STR_PAD_LEFT).'@loadtest.invalid';
                        $member = Member::firstOrNew(['email' => $email]);
                        if (! $member->exists) {
                            $member->fill(['name' => 'Fictional Member '.str_pad((string) $index, 4, '0', STR_PAD_LEFT), 'mobile' => '0900000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT), 'home_branch_id' => $branch->id]);
                            $member->is_active = $index % 50 !== 0;
                            if ($index <= 5) {
                                $member->forceFill(['portal_login' => $email, 'portal_password' => Hash::make('password')]);
                            }
                            $member->save(); // No claimed consent or agreement for seeded profiles.
                        }
                        $subscription = MemberSubscription::where('member_id', $member->id)->first();
                        if (! $subscription) {
                            $start = $today->addDays($index > 800 ? -70 : ($index > 600 && $index <= 700 ? 10 : ($index > 500 && $index <= 600 ? -26 : -10)))->utc();
                            $subscription = (new MemberSubscription)->forceFill(['member_id' => $member->id, 'membership_plan_id' => $plan->id, 'plan_name' => $plan->name, 'duration_amount' => 30, 'duration_unit' => 'days', 'price_centavos' => 120000, 'starts_at' => $start, 'ends_at' => $start->addDays(30), 'status' => $index > 800 ? MemberSubscriptionStatus::Expired : ($index > 700 ? MemberSubscriptionStatus::Suspended : MemberSubscriptionStatus::Active), 'operation' => 'assign', 'request_key' => (string) Str::uuid()]);
                            $subscription->save();
                        }
                        if ($index <= 500 && $index % 2 === 0 && ! Payment::where('member_id', $member->id)->exists()) {
                            (new Payment)->forceFill(['member_id' => $member->id, 'member_subscription_id' => $subscription->id, 'branch_id' => $member->home_branch_id, 'amount_centavos' => 60000, 'method' => PaymentMethod::Cash, 'recorded_by' => $owner->id, 'paid_at' => $today->subDays(5)->utc(), 'reference' => 'Fictional seed payment', 'request_key' => (string) Str::uuid()])->save();
                        }
                        if (! DB::table('attendance_records')->where('gym_id', $gym->id)->where('member_id', $member->id)->exists()) {
                            $rows = [];
                            foreach (range(1, 5) as $daysAgo) {
                                $rows[] = ['gym_id' => $gym->id, 'member_id' => $member->id, 'branch_id' => $member->home_branch_id, 'recorded_by' => $owner->id, 'source' => 'manual', 'checked_in_at' => $today->subDays($daysAgo)->addHours(9)->utc(), 'created_at' => now()->utc(), 'updated_at' => now()->utc()];
                            }
                            DB::table('attendance_records')->insert($rows);
                        }
                    }
                });
            }
            if (! GymWebsite::exists()) {
                (new GymWebsite)->forceFill(['published_at' => now()->utc(), 'published_content' => ['description' => 'Fictional website for local load testing.', 'about' => 'All members, prices and records in this gym are fictional testing data.', 'contact' => ['address' => 'Fictional development location', 'hours' => 'Fictional test hours'], 'plans' => [['name' => 'Fictional monthly membership', 'price_centavos' => 120000, 'duration' => '30 days', 'description' => 'Local testing only']], 'facilities' => [['name' => 'Fictional training area', 'description' => 'Sample facility']], 'announcements' => []]])->save();
            }
            if (! CoachSchedule::where('coach_id', $coach->id)->exists()) {
                foreach (range(0, 6) as $offset) {
                    (new CoachSchedule)->forceFill(['coach_id' => $coach->id, 'branch_id' => $main->id, 'title' => 'Fictional coaching session', 'starts_at' => $today->addDays($offset)->addHours(9)->utc(), 'ends_at' => $today->addDays($offset)->addHours(10)->utc()])->save();
                }
            }
            if (! WorkoutRoutine::exists()) {
                $snapshot = ['name' => 'Fictional beginner routine', 'notes' => 'Fictional workout for testing.', 'days' => [['name' => 'Day 1', 'exercises' => [['name' => 'Bodyweight squat', 'sets' => 3, 'reps' => '8', 'notes' => 'Controlled movement']]]]];
                $routine = (new WorkoutRoutine)->forceFill([...$snapshot, 'branch_id' => $main->id, 'created_by' => $coach->id, 'request_key' => (string) Str::uuid(), 'revision' => 1]);
                $routine->save();
                $member = Member::where('email', 'member0001@loadtest.invalid')->firstOrFail();
                (new MemberRoutineAssignment)->forceFill(['member_id' => $member->id, 'branch_id' => $main->id, 'workout_routine_id' => $routine->id, 'assigned_by' => $coach->id, 'routine_revision' => 1, 'snapshot' => $snapshot, 'assigned_at' => now()->utc()])->save();
            }
        });
        $this->command?->info('Kept 1,000 fictional members in Fictional Load Test Gym. Existing records and edits are preserved.');
    }

    private function account(Gym $gym, string $role, GymRole $gymRole): User
    {
        $user = User::firstOrNew(['email' => $role.'@loadtest.invalid']);
        if (! $user->exists) {
            $user->forceFill(['name' => 'Fictional '.ucfirst($role), 'password' => 'password', 'email_verified_at' => now()])->save();
            $gym->users()->attach($user, ['role' => $gymRole]);
        } elseif (! $gym->users()->whereKey($user->id)->exists()) {
            throw new RuntimeException('Test account belongs elsewhere. Refusing to change it.');
        }

        return $user;
    }
}
