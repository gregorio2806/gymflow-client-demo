<?php

namespace Database\Factories;

use App\Enums\GymStatus;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Fitness',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09'.fake()->numerify('#########'),
            'address' => fake()->city(),
        ];
    }

    /**
     * Indicate that the gym is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GymStatus::Suspended,
        ]);
    }
}
