<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => fake()->city().' branch',
            'address' => fake()->streetAddress(),
        ];
    }
}
