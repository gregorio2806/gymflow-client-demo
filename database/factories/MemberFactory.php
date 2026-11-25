<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Member;
use App\Tenancy\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gym_id' => app(CurrentGym::class)->id() ?? Gym::factory(),
            'name' => fake()->name(),
            'mobile' => '09'.fake()->numerify('#########'),
            'email' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
