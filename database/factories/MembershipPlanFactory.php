<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipPlan> */
class MembershipPlanFactory extends Factory
{
    public function definition(): array
    {
        return ['gym_id' => app(CurrentGym::class)->id() ?? Gym::factory(), 'name' => fake()->words(2, true), 'duration_amount' => 1, 'duration_unit' => 'months', 'price_centavos' => 150000, 'inclusions' => 'Gym floor access'];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
