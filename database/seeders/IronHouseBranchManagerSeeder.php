<?php

namespace Database\Seeders;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IronHouseBranchManagerSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('IronHouseBranchManagerSeeder must never run in production (known passwords).');
        }

        $gym = Gym::where('slug', 'iron-house-fitness')->firstOrFail();
        app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym) {
            $gym->subscription->update(['plan' => Plan::MultiBranch]);

            $manager = User::firstOrNew(['email' => 'manager@ironhouse.test']);
            $manager->forceFill([
                'name' => 'Morgan Branch Manager',
                'password' => 'password',
                'email_verified_at' => now(),
            ])->save();

            $gym->users()->syncWithoutDetaching([$manager->id => [
                'role' => GymRole::BranchManager,
                'branch_id' => $gym->mainBranch()->id,
            ]]);
        }));
    }
}
