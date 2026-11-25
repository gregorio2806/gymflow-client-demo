<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateGym
{
    /**
     * Create a gym on the chosen plan (trialing) and make the given user its owner.
     *
     * @param  array{name: string, phone?: string|null, address?: string|null}  $data
     */
    public function handle(User $owner, array $data, Plan $plan = Plan::Starter): Gym
    {
        return DB::transaction(function () use ($owner, $data, $plan) {
            $gym = Gym::create($data); // also creates its main branch + Starter subscription

            $gym->subscription->update(['plan' => $plan]);
            $gym->users()->attach($owner, ['role' => GymRole::Owner]);

            return $gym;
        });
    }
}
